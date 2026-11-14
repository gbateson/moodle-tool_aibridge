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

namespace tool_aibridge\action;

use tool_aibridge\audio;
use tool_aibridge\caller;
use tool_aibridge\provider;
use tool_aibridge\response;

/**
 * Generate speech from text.
 *
 * Request template tokens: {{TEXT}}, {{VOICE}}, {{LANGUAGE}}, and any token
 * set with set_token(). An empty voice uses the provider's default VOICE.
 * If the provider's return path is empty, the reply is the audio itself;
 * otherwise the path leads to base64 audio in a JSON reply.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_speech extends base {
    /** The most text sent in one request. Most speech APIs allow 4000-5000 characters. */
    const MAX_TEXT_LENGTH = 4000;

    /** @var string the text to speak. */
    protected $text;

    /** @var string the voice, or '' for the provider's default. */
    protected $voice;

    /** @var string the language code, e.g. "en-US". */
    protected $language;

    /**
     * Create the action.
     *
     * @param int $contextid where the request is made.
     * @param int $userid who makes the request.
     * @param string $text
     * @param string $voice the voice, or '' for the provider's default.
     * @param string $language the language code, e.g. "en-US".
     */
    public function __construct(int $contextid, int $userid, string $text, string $voice = '', string $language = '') {
        parent::__construct($contextid, $userid);
        $this->text = trim($text);
        $this->voice = trim($voice);
        $this->language = trim($language);
    }

    #[\Override]
    public function get_capabilities(): array {
        return [provider::CAPABILITY_SPEECH];
    }

    #[\Override]
    public function validate(): void {
        if ($this->text === '') {
            throw new \moodle_exception('errornotext', 'tool_aibridge');
        }
        if (\core_text::strlen($this->text) > self::MAX_TEXT_LENGTH) {
            throw new \moodle_exception('errortexttoolong', 'tool_aibridge', '', self::MAX_TEXT_LENGTH);
        }
    }

    #[\Override]
    public function get_tokens(provider $provider): array {
        $tokens = array_merge($this->tokens, ['TEXT' => $this->text]);
        if ($this->voice !== '') {
            $tokens['VOICE'] = $this->voice;
        }
        if ($this->language !== '') {
            $tokens['LANGUAGE'] = $this->language;
        }
        return $tokens;
    }

    #[\Override]
    public function parse_reply(string $reply, array $info, provider $provider): response {
        $record = $provider->get_record();
        $returnpath = trim((string) $record->returnpath);
        if ($returnpath === '') {
            // The reply is the audio itself.
            $audio = $reply;
            $mimetype = isset($info['content_type']) ? (string) $info['content_type'] : '';
        } else {
            $decoded = json_decode($reply, true);
            if (!is_array($decoded)) {
                throw new \moodle_exception('errorreplynotjson', 'tool_aibridge', '', $record->name);
            }
            $data = caller::extract_by_path($decoded, $returnpath);
            $audio = is_string($data) ? base64_decode($data, true) : false;
            if ($audio === false || $audio === '') {
                throw new \moodle_exception('errornotaudio', 'tool_aibridge');
            }
            $mimetype = audio::find_mimetype($decoded, $returnpath);
        }
        [$audio, $extension, $mimetype] = audio::identify($audio, $mimetype);
        return response::success(response::ROUTE_EXTERNAL, $provider->get_id(), $provider->get_name(), (string) $record->model)
                ->set_audio($audio, $mimetype, $extension);
    }

    #[\Override]
    public function get_prompt_for_log(): string {
        return $this->text;
    }

    #[\Override]
    public function get_reply_for_log(response $response): string {
        return $response->get_mimetype() . ' (' . display_size(strlen($response->get_audio())) . ')';
    }
}
