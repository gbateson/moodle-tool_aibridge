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

use tool_aibridge\action\generate_image;
use tool_aibridge\action\generate_speech;

/**
 * Make the media that a reply asks for, from its markers: [[AUDIO text]],
 * [[AUDIO a conversation]] or [[IMAGE prompt]]. See library\parser::find_media().
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class media {
    /**
     * Make one piece of media.
     *
     * @param array $item a media marker: type, text, attributes, marker.
     * @param int $contextid
     * @param int $userid
     * @param array $options for audio: voice, voices, language, pause; for images: aspectratio,
     *      quality, style; and providerid, component, purpose.
     * @return response with the audio or image.
     */
    public static function generate(array $item, int $contextid, int $userid, array $options = []): response {
        $text = trim($item['text']);
        $component = isset($options['component']) ? (string) $options['component'] : '';
        $providerid = isset($options['providerid']) ? (int) $options['providerid'] : 0;
        switch ($item['type']) {
            case 'AUDIO':
                if (dialogue::is_dialogue($text)) {
                    return dialogue::generate($contextid, $userid, $text, $options);
                }
                $action = new generate_speech(
                    $contextid,
                    $userid,
                    $text,
                    isset($options['voice']) ? (string) $options['voice'] : '',
                    isset($options['language']) ? (string) $options['language'] : ''
                );
                $action->set_purpose($component, isset($options['purpose']) ? (string) $options['purpose'] : 'audio');
                return manager::process($action, $providerid);
            case 'IMAGE':
                $action = new generate_image(
                    $contextid,
                    $userid,
                    $text,
                    isset($options['aspectratio']) ? (string) $options['aspectratio'] : 'square',
                    isset($options['quality']) ? (string) $options['quality'] : 'standard',
                    isset($options['style']) ? (string) $options['style'] : ''
                );
                $action->set_purpose($component, isset($options['purpose']) ? (string) $options['purpose'] : 'image');
                return manager::process($action, $providerid);
        }
        return response::failure('mediatype', get_string('errormediatype', 'tool_aibridge', $item['type']));
    }
}
