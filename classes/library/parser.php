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
 * Read the reply to a prompt: remove chatter and markdown, check it against
 * its output format, count the questions, and find the media markers, e.g.
 * [[AUDIO They are talking about a broken machine.]] or [[IMAGE A photo of ...]].
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class parser {
    /** A media marker: [[AUDIO text]], [[IMAGE text]] or [[VIDEO text]], optionally with attributes. */
    const MEDIA_PATTERN = '/\[\[(AUDIO|IMAGE|VIDEO)\b(.*?)\]\]/s';

    /** A sub-question in embedded answers (Cloze), e.g. {1:MCVS:=right~wrong}. */
    const CLOZE_PATTERN = '/\{\d*:(MULTICHOICE|MULTICHOICE_V|MULTICHOICE_H|MULTICHOICE_S|MULTICHOICE_VS|MULTICHOICE_HS|' .
            'MULTIRESPONSE|MULTIRESPONSE_H|MULTIRESPONSE_S|MULTIRESPONSE_HS|MC|MCV|MCH|MCS|MCVS|MCHS|MR|MRH|MRS|MRHS|' .
            'SHORTANSWER|SHORTANSWER_C|SA|MW|SAC|MWC|NUMERICAL|NM):/';

    /**
     * Read a reply.
     *
     * @param string $reply
     * @param string $qformat gift, multianswer, xml, json or text.
     * @return parsed
     */
    public static function parse(string $reply, string $qformat): parsed {
        $result = new parsed();
        $text = self::clean($reply, $qformat);
        if ($text !== trim(str_replace(["\r\n", "\r"], "\n", $reply))) {
            $result->add_warning(get_string('warningreplycleaned', 'tool_aibridge'));
        }
        $result->set_text($text);
        $result->set_media(self::find_media($text));
        if ($text === '') {
            $result->add_error(get_string('errorreplyempty', 'tool_aibridge'));
            return $result;
        }
        switch ($qformat) {
            case 'gift':
                self::check_gift($text, $result);
                break;
            case 'multianswer':
                $count = preg_match_all(self::CLOZE_PATTERN, $text);
                if ($count == 0) {
                    $result->add_error(get_string('errornocloze', 'tool_aibridge'));
                }
                $result->set_count($count);
                break;
            case 'xml':
                self::check_xml($text, $result);
                break;
            case 'json':
                $decoded = json_decode($text, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $result->add_error(get_string('errorreplynotjsonformat', 'tool_aibridge', json_last_error_msg()));
                } else {
                    $result->set_count(is_array($decoded) && array_keys($decoded) === range(0, count($decoded) - 1)
                            ? count($decoded) : 1);
                }
                break;
            default:
                $result->set_count(1);
        }
        return $result;
    }

    /**
     * Remove what is not part of the output: markdown code fences, and chatter before and after it.
     *
     * @param string $reply
     * @param string $qformat
     * @return string
     */
    public static function clean(string $reply, string $qformat): string {
        $text = str_replace(["\r\n", "\r"], "\n", $reply);
        // Markdown code fences: three backticks, optionally with a language, e.g. gift.
        if (preg_match('/^`{3}[a-zA-Z]*[ \t]*\n(.*?)\n`{3}[ \t]*$/ms', $text, $match)) {
            $text = $match[1];
        }
        $text = trim($text);
        switch ($qformat) {
            case 'gift':
                // From the first question, comment or category, to the end of the last question.
                if (preg_match('/^(::|\/\/|\$CATEGORY:)/m', $text, $match, PREG_OFFSET_CAPTURE)) {
                    $text = substr($text, $match[0][1]);
                }
                $end = strrpos($text, '}');
                if ($end !== false) {
                    $text = substr($text, 0, $end + 1);
                }
                break;
            case 'xml':
                if (preg_match('/<\?xml|<quiz\b/', $text, $match, PREG_OFFSET_CAPTURE)) {
                    $text = substr($text, $match[0][1]);
                }
                $end = strrpos($text, '</quiz>');
                if ($end !== false) {
                    $text = substr($text, 0, $end + 7);
                }
                break;
            case 'json':
                if (preg_match('/[\[{]/', $text, $match, PREG_OFFSET_CAPTURE)) {
                    $text = substr($text, $match[0][1]);
                }
                $end = max((int) strrpos($text, '}'), (int) strrpos($text, ']'));
                if ($end) {
                    $text = substr($text, 0, $end + 1);
                }
                break;
            case 'multianswer':
                $start = strpos($text, '<');
                $end = strrpos($text, '>');
                if ($start !== false && $end !== false && $end > $start) {
                    $text = substr($text, $start, $end - $start + 1);
                }
                break;
        }
        return trim($text);
    }

    /**
     * Find the media markers.
     *
     * @param string $text
     * @return array of [type, text, attributes, marker], e.g. ['AUDIO', 'Hello.', 'class="nomediaplugin"', '[[AUDIO ...]]'].
     */
    public static function find_media(string $text): array {
        $media = [];
        if (preg_match_all(self::MEDIA_PATTERN, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $inner = trim($match[2]);
                // Attributes come first, e.g. class="nomediaplugin".
                $attributes = '';
                while (preg_match('/^([a-z-]+="[^"]*")\s*/', $inner, $attr)) {
                    $attributes = trim($attributes . ' ' . $attr[1]);
                    $inner = substr($inner, strlen($attr[0]));
                }
                $media[] = [
                    'type' => $match[1],
                    'text' => trim($inner),
                    'attributes' => $attributes,
                    'marker' => $match[0],
                ];
            }
        }
        return $media;
    }

    /**
     * Check GIFT, with Moodle's own GIFT reader. Media markers are replaced
     * first, since they are replaced before the questions are imported.
     *
     * @param string $text
     * @param parsed $result
     */
    protected static function check_gift(string $text, parsed $result): void {
        global $CFG;
        $i = 0;
        $text = preg_replace_callback(self::MEDIA_PATTERN, function ($match) use (&$i) {
            $i++;
            return 'MEDIA' . $i;
        }, $text);
        // Moodle's GIFT reader accepts a question whose answers are not closed.
        $unescaped = preg_replace('/\\\\[{}]/', '', $text);
        if (substr_count($unescaped, '{') != substr_count($unescaped, '}')) {
            $result->add_error(get_string('errorgiftbraces', 'tool_aibridge'));
        }
        require_once($CFG->libdir . '/questionlib.php');
        require_once($CFG->dirroot . '/question/format.php');
        require_once($CFG->dirroot . '/question/format/gift/format.php');
        // The method that reads GIFT is protected, so it is called through a subclass.
        $format = new class extends \qformat_gift {
            /**
             * Read GIFT questions.
             *
             * @param array $lines
             * @return array
             */
            public function read(array $lines) {
                return $this->readquestions($lines);
            }
        };
        ob_start();
        try {
            $questions = $format->read(explode("\n", $text));
        } catch (\Throwable $e) {
            $questions = [];
            $result->add_error($e->getMessage());
        }
        $output = trim(strip_tags(ob_get_clean()));
        if ($output !== '') {
            $result->add_error($output);
        }
        $count = 0;
        foreach ((array) $questions as $question) {
            if (isset($question->qtype) && !in_array($question->qtype, ['category', 'description'])) {
                $count++;
            }
        }
        if ($count == 0 && !$result->get_errors()) {
            $result->add_error(get_string('errornoquestions', 'tool_aibridge'));
        }
        $result->set_count($count);
    }

    /**
     * Check Moodle XML.
     *
     * @param string $text
     * @param parsed $result
     */
    protected static function check_xml(string $text, parsed $result): void {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($text);
        if ($xml === false) {
            $errors = [];
            foreach (libxml_get_errors() as $error) {
                $errors[] = trim($error->message) . ' (' . $error->line . ')';
            }
            libxml_clear_errors();
            $result->add_error(get_string('errorreplynotxml', 'tool_aibridge', implode('; ', array_slice($errors, 0, 3))));
        } else {
            $count = 0;
            foreach ($xml->xpath('//question') as $question) {
                if ((string) $question['type'] !== 'category') {
                    $count++;
                }
            }
            if ($count == 0) {
                $result->add_error(get_string('errornoquestions', 'tool_aibridge'));
            }
            $result->set_count($count);
        }
        libxml_use_internal_errors($previous);
    }
}
