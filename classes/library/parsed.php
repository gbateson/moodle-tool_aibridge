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
 * A reply that was read: its cleaned text, the number of questions, media markers, errors and warnings.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class parsed {
    /** @var string the cleaned text. */
    protected $text = '';

    /** @var int the number of questions, or items. */
    protected $count = 0;

    /** @var array the media markers. */
    protected $media = [];

    /** @var string[] errors. */
    protected $errors = [];

    /** @var string[] warnings. */
    protected $warnings = [];

    /**
     * Set the cleaned text.
     *
     * @param string $text
     */
    public function set_text(string $text): void {
        $this->text = $text;
    }

    /**
     * Set the number of questions.
     *
     * @param int $count
     */
    public function set_count(int $count): void {
        $this->count = $count;
    }

    /**
     * Set the media markers.
     *
     * @param array $media
     */
    public function set_media(array $media): void {
        $this->media = $media;
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
        $this->warnings[] = $warning;
    }

    /**
     * Get the cleaned text.
     *
     * @return string
     */
    public function get_text(): string {
        return $this->text;
    }

    /**
     * Get the number of questions.
     *
     * @return int
     */
    public function get_count(): int {
        return $this->count;
    }

    /**
     * Get the media markers.
     *
     * @param string $type AUDIO, IMAGE or VIDEO, or '' for all.
     * @return array of [type, text, attributes, marker].
     */
    public function get_media(string $type = ''): array {
        if ($type === '') {
            return $this->media;
        }
        return array_values(array_filter($this->media, function ($item) use ($type) {
            return $item['type'] === $type;
        }));
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
     * Is the reply usable?
     *
     * @return bool
     */
    public function is_valid(): bool {
        return empty($this->errors);
    }
}
