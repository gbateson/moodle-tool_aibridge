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

use tool_aibridge\action\generate_speech;

/**
 * Make the audio of a conversation between several speakers.
 *
 * A script has a header, with one line for each speaker, and then the lines
 * of the conversation, e.g.
 *
 *     A=woman (customer calling with complaint)
 *     B=man (staff on a customer help line)
 *     A: Hello. I'm calling about a coffee machine.
 *     B: Oh, I'm sorry to hear that.
 *
 * Each speaker gets a different voice, chosen by gender from the provider's
 * default values VOICES_FEMALE, VOICES_MALE and VOICES (lists separated by
 * commas), unless the voices are given. Each line is sent to the same speech
 * provider, and is logged; then the pieces are joined, with a short pause
 * between them if they are WAV. The request template can use {{SPEAKER}},
 * {{GENDER}} and {{ROLE}}, and {{AUDIOFORMAT}} is set to "wav". The response
 * also has WebVTT subtitles, with the speaker and text of each line.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dialogue {
    /** The most lines in one conversation. */
    const MAX_LINES = 50;

    /** A header line: "A=woman (customer)". */
    const HEADER_PATTERN = '/^([A-Za-z0-9_]{1,20})\s*=\s*([^\s(]+)\s*(?:\((.*)\))?\s*$/';

    /** A line of the conversation: "A: Hello." */
    const LINE_PATTERN = '/^([A-Za-z0-9_]{1,20})\s*:\s*(.*)$/';

    /** Words for female speakers. */
    const FEMALE = ['woman', 'female', 'girl', 'lady', 'f', 'w'];

    /** Words for male speakers. */
    const MALE = ['man', 'male', 'boy', 'gentleman', 'm'];

    /**
     * Read a script.
     *
     * @param string $script
     * @return array ['speakers' => [name => [gender, role]], 'lines' => [[speaker, text], ...]]
     */
    public static function parse(string $script): array {
        $speakers = [];
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', $script) as $text) {
            $text = trim($text);
            if ($text === '') {
                continue;
            }
            if (empty($lines) && preg_match(self::HEADER_PATTERN, $text, $match)) {
                $speakers[$match[1]] = [
                    'gender' => self::get_gender($match[2]),
                    'role' => isset($match[3]) ? trim($match[3]) : '',
                ];
            } else if (preg_match(self::LINE_PATTERN, $text, $match) && trim($match[2]) !== '') {
                $lines[] = [$match[1], trim($match[2])];
                if (!isset($speakers[$match[1]])) {
                    $speakers[$match[1]] = ['gender' => '', 'role' => ''];
                }
            } else if ($lines) {
                // A line that goes on from the line before.
                $lines[count($lines) - 1][1] .= ' ' . $text;
            }
        }
        return ['speakers' => $speakers, 'lines' => $lines];
    }

    /**
     * Is this text a conversation, rather than one piece of speech?
     *
     * @param string $text
     * @return bool
     */
    public static function is_dialogue(string $text): bool {
        $parsed = self::parse($text);
        return count($parsed['lines']) >= 2 || (count($parsed['lines']) == 1 && count($parsed['speakers']) >= 1
            && preg_match(self::HEADER_PATTERN, trim(strtok($text, "\n"))));
    }

    /**
     * Get the gender from a word such as "woman" or "male".
     *
     * @param string $word
     * @return string female, male or ''.
     */
    public static function get_gender(string $word): string {
        $word = \core_text::strtolower(trim($word));
        if (in_array($word, self::FEMALE)) {
            return 'female';
        }
        if (in_array($word, self::MALE)) {
            return 'male';
        }
        return '';
    }

    /**
     * Choose a different voice for each speaker.
     *
     * @param array $speakers name => [gender, role].
     * @param array $defaults the provider's default token values.
     * @param array $voices name => voice, for speakers whose voices are already chosen.
     * @return array name => voice ('' for the provider's default voice).
     */
    public static function choose_voices(array $speakers, array $defaults, array $voices = []): array {
        $lists = [];
        foreach (['female' => 'VOICES_FEMALE', 'male' => 'VOICES_MALE', '' => 'VOICES'] as $gender => $key) {
            $lists[$gender] = isset($defaults[$key]) ? array_values(array_filter(array_map(
                'trim',
                explode(',', $defaults[$key])
            ))) : [];
        }
        if (empty($lists[''])) {
            $lists[''] = array_merge($lists['female'], $lists['male']);
        }
        $used = array_values($voices);
        $chosen = [];
        foreach ($speakers as $name => $speaker) {
            if (isset($voices[$name]) && $voices[$name] !== '') {
                $chosen[$name] = $voices[$name];
                continue;
            }
            $list = $lists[$speaker['gender']] ? $lists[$speaker['gender']] : $lists[''];
            $voice = '';
            foreach ($list as $candidate) {
                if (!in_array($candidate, $used)) {
                    $voice = $candidate;
                    break;
                }
            }
            if ($voice === '' && $list) {
                // More speakers than voices: use the voices again.
                $voice = $list[count($chosen) % count($list)];
            }
            $chosen[$name] = $voice;
            $used[] = $voice;
        }
        return $chosen;
    }

    /**
     * Make the audio of a conversation.
     *
     * @param int $contextid where the request is made.
     * @param int $userid who makes the request.
     * @param string $script
     * @param array $options voices (name => voice), language, pause (milliseconds), providerid, component, purpose.
     * @return response with the joined audio, and a description of the voices as its content.
     */
    public static function generate(int $contextid, int $userid, string $script, array $options = []): response {
        $parsed = self::parse($script);
        if (empty($parsed['lines'])) {
            return response::failure('nodialogue');
        }
        if (count($parsed['lines']) > self::MAX_LINES) {
            return response::failure('dialoguetoolong', get_string('errordialoguetoolong', 'tool_aibridge', self::MAX_LINES));
        }
        $language = isset($options['language']) ? (string) $options['language'] : '';
        $component = isset($options['component']) ? (string) $options['component'] : '';
        $purpose = isset($options['purpose']) ? (string) $options['purpose'] : 'dialogue';
        $pause = isset($options['pause']) ? (int) $options['pause'] : 400;

        // Every line is spoken by the same provider, so that the pieces can be joined.
        $probe = new generate_speech($contextid, $userid, 'probe');
        $providerid = isset($options['providerid']) ? (int) $options['providerid'] : 0;
        $provider = null;
        foreach (manager::get_routes($probe, $providerid) as $route) {
            if ($route instanceof provider) {
                $provider = $route;
                break;
            }
        }
        if (!$provider) {
            return response::failure('noproviders');
        }
        $voices = self::choose_voices(
            $parsed['speakers'],
            $provider->get_token_defaults(),
            isset($options['voices']) ? (array) $options['voices'] : []
        );

        $pieces = [];
        $cues = [];
        $response = null;
        foreach ($parsed['lines'] as $i => $line) {
            [$name, $text] = $line;
            $speaker = $parsed['speakers'][$name];
            $action = new generate_speech($contextid, $userid, $text, $voices[$name], $language);
            $action->set_purpose($component, $purpose);
            $action->set_token('SPEAKER', $name);
            $action->set_token('GENDER', $speaker['gender']);
            $action->set_token('ROLE', $speaker['role']);
            $action->set_token('AUDIOFORMAT', 'wav');
            $response = manager::process($action, $provider->get_id());
            if (!$response->get_success()) {
                return response::failure(
                    $response->get_errorcode(),
                    get_string(
                        'errordialogueline',
                        'tool_aibridge',
                        ['line' => $i + 1, 'error' => $response->get_error()]
                    ),
                    $response->get_route(),
                    $provider->get_id(),
                    $provider->get_name()
                );
            }
            $pieces[] = [$response->get_audio(), $response->get_extension(), $response->get_mimetype()];
            $cues[] = [audio::get_duration($response->get_audio(), $response->get_extension()), $name . ': ' . $text];
        }
        try {
            [$audio, $extension, $mimetype] = audio::join($pieces, $pause);
        } catch (\moodle_exception $e) {
            return response::from_exception(
                $e,
                $e->getMessage(),
                response::ROUTE_EXTERNAL,
                $provider->get_id(),
                $provider->get_name()
            );
        }
        $description = [];
        foreach ($voices as $name => $voice) {
            $description[] = $name . ': ' . ($voice === '' ? get_string('defaultvoice', 'tool_aibridge') : $voice);
        }
        return response::success(response::ROUTE_EXTERNAL, $provider->get_id(), $provider->get_name(), $response->get_model())
            ->set_audio($audio, $mimetype, $extension)
            ->set_content(implode(', ', $description))
            ->set_captions(audio::make_captions($cues, ($extension === 'wav') ? $pause : 0))
            ->set_logid($response->get_logid());
    }
}
