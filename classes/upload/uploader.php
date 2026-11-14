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

namespace tool_aibridge\upload;

use tool_aibridge\provider;

/**
 * Uploads a file to a provider before the request, so that the request
 * refers to the file instead of carrying it, e.g. for large recordings.
 *
 * Each provider has its own way of uploading files, so each way is a
 * class here. The request then uses the {{FILE_URI}} token.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class uploader {
    /** Google Gemini's resumable uploads (the Files API). */
    const PROTOCOL_GEMINI = 'gemini';

    /**
     * Get the ways of uploading, for a menu.
     *
     * @return string[] protocol => name.
     */
    public static function get_protocol_options(): array {
        return [self::PROTOCOL_GEMINI => get_string('uploadprotocol_gemini', 'tool_aibridge')];
    }

    /**
     * Get the uploader for a protocol.
     *
     * @param string $protocol
     * @return uploader
     * @throws \moodle_exception if there is no such protocol.
     */
    public static function create(string $protocol): uploader {
        switch ($protocol) {
            case self::PROTOCOL_GEMINI:
                return new gemini();
        }
        throw new \moodle_exception('erroruploadprotocol', 'tool_aibridge', '', $protocol);
    }

    /**
     * Upload a file.
     *
     * @param \stored_file $file
     * @param provider $provider
     * @return array the file's tokens: FILE_URI, FILE_MIMETYPE and FILE_NAME.
     * @throws \moodle_exception
     */
    abstract public function upload(\stored_file $file, provider $provider): array;
}
