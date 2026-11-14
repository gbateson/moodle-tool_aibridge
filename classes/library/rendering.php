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
 * A rendered prompt: its text, the templates and versions it used, and any errors and warnings.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rendering {
    /** @var template the prompt. */
    protected $prompt;

    /** @var template|null the format. */
    protected $format = null;

    /** @var string the text to send. */
    protected $text = '';

    /** @var template[] short name => template, for each template used. */
    protected $templates = [];

    /** @var string[] errors, which stop the prompt being sent. */
    protected $errors = [];

    /** @var string[] warnings. */
    protected $warnings = [];

    /** @var string[] the names of parameters that hold personal data. */
    protected $personal = [];

    /**
     * Start a rendering.
     *
     * @param template $prompt
     */
    public function __construct(template $prompt) {
        $this->prompt = $prompt;
        $this->add_template($prompt);
    }

    /**
     * Note that a template was used.
     *
     * @param template $template
     */
    public function add_template(template $template): void {
        $this->templates[$template->get_shortname()] = $template;
    }

    /**
     * Set the format.
     *
     * @param template $format
     */
    public function set_format(template $format): void {
        $this->format = $format;
    }

    /**
     * Set the text.
     *
     * @param string $text
     */
    public function set_text(string $text): void {
        $this->text = $text;
    }

    /**
     * Add an error.
     *
     * @param string $error
     */
    public function add_error(string $error): void {
        $this->errors[] = $error;
    }

    /**
     * Add a warning.
     *
     * @param string $warning
     */
    public function add_warning(string $warning): void {
        if (!in_array($warning, $this->warnings)) {
            $this->warnings[] = $warning;
        }
    }

    /**
     * Note that a parameter holding personal data was used.
     *
     * @param string $name
     */
    public function add_personal(string $name): void {
        $this->personal[$name] = $name;
    }

    /**
     * Get the prompt.
     *
     * @return template
     */
    public function get_prompt(): template {
        return $this->prompt;
    }

    /**
     * Get the format, if there is one.
     *
     * @return template|null
     */
    public function get_format(): ?template {
        return $this->format;
    }

    /**
     * Get the output format of the reply, e.g. "gift".
     *
     * @return string
     */
    public function get_qformat(): string {
        return $this->format ? (string) $this->format->get_record()->qformat : 'text';
    }

    /**
     * Get the text to send.
     *
     * @return string
     */
    public function get_text(): string {
        return $this->text;
    }

    /**
     * Get the templates used.
     *
     * @return template[] short name => template.
     */
    public function get_templates(): array {
        return $this->templates;
    }

    /**
     * Get the versions of the templates used.
     *
     * @return int[] short name => version.
     */
    public function get_versions(): array {
        $versions = [];
        foreach ($this->templates as $shortname => $template) {
            $versions[$shortname] = $template->get_version();
        }
        return $versions;
    }

    /**
     * Get the parameters declared by all the templates used.
     *
     * @return array name => parameter.
     */
    public function get_parameters(): array {
        $parameters = [];
        foreach (array_reverse($this->templates) as $template) {
            $parameters = array_merge($parameters, $template->get_parameters());
        }
        return $parameters;
    }

    /**
     * Get the errors.
     *
     * @return string[]
     */
    public function get_errors(): array {
        return $this->errors;
    }

    /**
     * Get the warnings.
     *
     * @return string[]
     */
    public function get_warnings(): array {
        return $this->warnings;
    }

    /**
     * Get the names of parameters that hold personal data.
     *
     * @return string[]
     */
    public function get_personal(): array {
        return array_values($this->personal);
    }

    /**
     * Can the prompt be sent?
     *
     * @return bool
     */
    public function is_valid(): bool {
        return empty($this->errors) && $this->text !== '';
    }
}
