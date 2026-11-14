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

namespace tool_aibridge;

/**
 * An AI provider could not be reached, or returned an error.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class request_exception extends \moodle_exception {
    /** @var int the HTTP status code, or 0 if the request could not be sent. */
    public $httpcode = 0;

    /**
     * Create the exception.
     *
     * @param string $detail what went wrong, with any API key removed.
     * @param int $httpcode
     */
    public function __construct(string $detail, int $httpcode = 0) {
        $this->httpcode = $httpcode;
        parent::__construct('errorrequest', 'tool_aibridge', '', $detail);
    }
}
