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
 * Work out what kind of image a provider sent back.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class image {
    /**
     * Work out what kind of image this is, from its first bytes.
     *
     * @param string $image
     * @return array [string $extension, string $mimetype]
     * @throws \moodle_exception if this is not an image.
     */
    public static function identify(string $image): array {
        $start = substr($image, 0, 12);
        if (strncmp($start, "\x89PNG", 4) === 0) {
            return ['png', 'image/png'];
        }
        if (strncmp($start, "\xFF\xD8\xFF", 3) === 0) {
            return ['jpg', 'image/jpeg'];
        }
        if (strncmp($start, 'GIF8', 4) === 0) {
            return ['gif', 'image/gif'];
        }
        if (strncmp($start, 'RIFF', 4) === 0 && substr($start, 8, 4) === 'WEBP') {
            return ['webp', 'image/webp'];
        }
        // The text of a JSON or HTML reply, e.g. an error page, is not an image.
        throw new \moodle_exception('errornotimage', 'tool_aibridge');
    }

    /**
     * Get an image from a URL, e.g. a provider that replies with a link to the image.
     *
     * @param string $url
     * @return string the image.
     * @throws \moodle_exception if it cannot be downloaded.
     */
    public static function download(string $url): string {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $image = (string) $curl->get($url, [], [
            'CURLOPT_CONNECTTIMEOUT' => caller::CONNECT_TIMEOUT,
            'CURLOPT_TIMEOUT' => caller::REQUEST_TIMEOUT,
        ]);
        $info = $curl->get_info();
        if ($curl->get_errno() || (!empty($info['http_code']) && $info['http_code'] >= 400) || $image === '') {
            throw new \moodle_exception('errorimagedownload', 'tool_aibridge');
        }
        return $image;
    }
}
