<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_aibridge\library;

/**
 * Turn a prompt template into the text that is sent to the AI.
 *
 * 1. Blocks: a prompt that extends another replaces the parent's
 *    {{block:name}}default text{{endblock}} sections with its own.
 * 2. Format: {{output-format}} becomes the format linked to the question
 *    type (its template, instructions and examples), and {{question-format}}
 *    becomes the name of its output format, e.g. "GIFT".
 * 3. Includes: {{include:shortname}} becomes the body of that template.
 * 4. Parameters: {{name}} becomes the value given, or the declared default.
 *    Names are lower case, so the upper-case tokens of provider request
 *    templates, e.g. {{PROMPT}}, are never touched.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer {
    /** The most levels of extends and include. */
    const MAX_DEPTH = 5;

    /** A block: {{block:name}}text{{endblock}}. */
    const BLOCK_PATTERN = '/\{\{block:([a-z0-9_-]+)\}\}(.*?)\{\{endblock\}\}/s';

    /** An include: {{include:shortname}}. */
    const INCLUDE_PATTERN = '/\{\{include:([a-z0-9_:.-]+)\}\}/';

    /** A parameter: {{name}}. */
    const PARAMETER_PATTERN = '/\{\{([a-z0-9][a-z0-9_-]*)\}\}/';

    /** Names of question types, used for {{type-of-questions}}. */
    const QTYPE_NAMES = [
        'multichoice' => 'multiple-choice',
        'truefalse' => 'true/false',
        'match' => 'matching',
        'shortanswer' => 'short-answer',
        'multianswer' => 'embedded-answer (Cloze)',
        'essay' => 'essay',
        'ordering' => 'ordering',
        'gapselect' => 'select-missing-words',
        'ddwtos' => 'drag-and-drop-into-text',
    ];

    /** Names of output formats, used for {{question-format}}. */
    const QFORMAT_NAMES = [
        'gift' => 'GIFT',
        'multianswer' => 'embedded answers (Cloze)',
        'xml' => 'Moodle XML',
        'json' => 'JSON',
        'text' => 'plain text',
    ];

    /** @var callable finds a template by short name: function(string $shortname): ?template */
    protected $finder;

    /** @var rendering the result being built. */
    protected $result;

    /**
     * Create a renderer.
     *
     * @param callable $finder finds a template that may be used, by short name.
     */
    public function __construct(callable $finder) {
        $this->finder = $finder;
    }

    /**
     * Render a prompt.
     *
     * @param template $prompt
     * @param array $values parameter name => value. The value "format" chooses a format by short name.
     * @param string $qtype the question type, e.g. "multichoice", used to choose the format.
     * @return rendering
     */
    public function render(template $prompt, array $values = [], string $qtype = ''): rendering {
        $this->result = new rendering($prompt);

        // 1. Blocks from the chain of prompts.
        $text = $this->apply_blocks($prompt);

        // 2. The output format.
        $format = $this->choose_format($prompt, $values, $qtype);
        if ($format) {
            $this->result->add_template($format);
            if (!isset($values['output-format'])) {
                $values['output-format'] = self::format_text($format);
            }
            $qformat = (string) $format->get_record()->qformat;
            if (!isset($values['question-format']) && isset(self::QFORMAT_NAMES[$qformat])) {
                $values['question-format'] = self::QFORMAT_NAMES[$qformat];
            }
            $this->result->set_format($format);
        } else if (strpos($text, '{{output-format}}') !== false && !isset($values['output-format'])) {
            $this->result->add_error(get_string('errornoformat', 'tool_aibridge', $qtype));
        }
        if ($qtype !== '' && !isset($values['type-of-questions'])) {
            $values['type-of-questions'] = isset(self::QTYPE_NAMES[$qtype]) ? self::QTYPE_NAMES[$qtype] : $qtype;
        }

        // 3. Includes. The format is put in first, so that its own includes are found too.
        if (isset($values['output-format'])) {
            $text = str_replace('{{output-format}}', $values['output-format'], $text);
            unset($values['output-format']);
        }
        $text = $this->apply_includes($text, 0);

        // 4. Parameters.
        $text = $this->apply_parameters($text, $values);

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+\n/', "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $this->result->set_text(trim($text));
        return $this->result;
    }

    /**
     * Get the text of a format: its template, then its instructions and examples.
     *
     * @param template $format
     * @return string
     */
    public static function format_text(template $format): string {
        $record = $format->get_record();
        $parts = [];
        foreach (['body', 'instructions', 'examples'] as $field) {
            if (trim((string) $record->$field) !== '') {
                $parts[] = trim((string) $record->$field);
            }
        }
        return implode("\n\n", $parts);
    }

    /**
     * Get the body of a prompt, with the blocks of each prompt in its chain of parents.
     *
     * @param template $prompt
     * @return string
     */
    protected function apply_blocks(template $prompt): string {
        // The chain from the prompt up to its top parent.
        $chain = [$prompt];
        $seen = [$prompt->get_id() => true];
        $template = $prompt;
        while ($parent = $template->get_parent()) {
            if (isset($seen[$parent->get_id()]) || count($chain) > self::MAX_DEPTH) {
                $this->result->add_error(get_string('errorextendsloop', 'tool_aibridge', $template->get_shortname()));
                break;
            }
            $seen[$parent->get_id()] = true;
            $chain[] = $parent;
            $this->result->add_template($parent);
            $template = $parent;
        }

        // The blocks of each child replace those of its parents.
        $top = array_pop($chain);
        $blocks = [];
        foreach (array_reverse($chain) as $child) {
            $body = $child->get_body();
            if (preg_match_all(self::BLOCK_PATTERN, $body, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $blocks[$match[1]] = $match[2];
                }
            }
            if (trim(preg_replace(self::BLOCK_PATTERN, '', $body)) !== '') {
                $this->result->add_warning(get_string('warningoutsideblocks', 'tool_aibridge', $child->get_shortname()));
            }
        }

        $text = preg_replace_callback(self::BLOCK_PATTERN, function ($match) use ($blocks) {
            return array_key_exists($match[1], $blocks) ? $blocks[$match[1]] : $match[2];
        }, $top->get_body());
        foreach (array_keys($blocks) as $name) {
            if (strpos($top->get_body(), '{{block:' . $name . '}}') === false) {
                $this->result->add_warning(get_string('warningunknownblock', 'tool_aibridge', $name));
            }
        }
        return $text;
    }

    /**
     * Choose the format: one named in the values, or the one linked to the question type,
     * or the one linked to "*", or else the only one linked. A child's links are added to its parents'.
     *
     * @param template $prompt
     * @param array $values
     * @param string $qtype
     * @return template|null
     */
    protected function choose_format(template $prompt, array $values, string $qtype): ?template {
        if (!empty($values['format'])) {
            $shortname = (string) $values['format'];
        } else {
            $links = [];
            $chain = [];
            $template = $prompt;
            while ($template && count($chain) <= self::MAX_DEPTH) {
                $chain[] = $template;
                $template = $template->get_parent();
            }
            foreach (array_reverse($chain) as $template) {
                $links = array_merge($links, $template->get_formatlinks());
            }
            if ($qtype !== '' && isset($links[$qtype])) {
                $shortname = $links[$qtype];
            } else if (isset($links['*'])) {
                $shortname = $links['*'];
            } else if (count($links) == 1) {
                $shortname = reset($links);
            } else {
                return null;
            }
        }
        $format = $this->find($shortname);
        if ($format && $format->get_type() !== template::TYPE_FORMAT) {
            $this->result->add_error(get_string('errornotaformat', 'tool_aibridge', $shortname));
            return null;
        }
        return $format;
    }

    /**
     * Put in the included templates.
     *
     * @param string $text
     * @param int $depth
     * @return string
     */
    protected function apply_includes(string $text, int $depth): string {
        return preg_replace_callback(self::INCLUDE_PATTERN, function ($match) use ($depth) {
            if ($depth >= self::MAX_DEPTH) {
                $this->result->add_error(get_string('errorincludeloop', 'tool_aibridge', $match[1]));
                return '';
            }
            $template = $this->find($match[1]);
            if (!$template) {
                return $match[0];
            }
            $this->result->add_template($template);
            $body = ($template->get_type() === template::TYPE_FORMAT) ? self::format_text($template) : $template->get_body();
            return $this->apply_includes(trim($body), $depth + 1);
        }, $text);
    }

    /**
     * Put in the parameter values, or the declared defaults.
     *
     * @param string $text
     * @param array $values
     * @return string
     */
    protected function apply_parameters(string $text, array $values): string {
        $declared = $this->result->get_parameters();
        foreach ($declared as $name => $parameter) {
            $has = isset($values[$name]) && trim((string) $values[$name]) !== '';
            if (!$has && $parameter['default'] !== '') {
                $values[$name] = $parameter['default'];
                $has = true;
            }
            if (!$has && $parameter['required'] && strpos($text, '{{' . $name . '}}') !== false) {
                $this->result->add_error(get_string('errorparameterrequired', 'tool_aibridge', $name));
            }
            if ($has && $parameter['personal']) {
                $this->result->add_personal($name);
            }
        }
        return preg_replace_callback(self::PARAMETER_PATTERN, function ($match) use ($values) {
            if (array_key_exists($match[1], $values) && is_scalar($values[$match[1]])) {
                return (string) $values[$match[1]];
            }
            $this->result->add_warning(get_string('warningunknownparameter', 'tool_aibridge', $match[1]));
            return $match[0];
        }, $text);
    }

    /**
     * Find a template that may be used.
     *
     * @param string $shortname
     * @return template|null
     */
    protected function find(string $shortname): ?template {
        $template = call_user_func($this->finder, $shortname);
        if (!$template) {
            $this->result->add_error(get_string('errortemplatenotfound', 'tool_aibridge', $shortname));
        }
        return $template;
    }
}
