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

use tool_aibridge\caller;
use tool_aibridge\image;
use tool_aibridge\provider;
use tool_aibridge\response;

/**
 * Generate an image from a prompt.
 *
 * The options are the same as those of Moodle's own AI subsystem: an aspect
 * ratio of "square", "landscape" or "portrait", a quality of "standard" or
 * "hd", and a style, e.g. "vivid" or "natural".
 *
 * Request template tokens:
 * - {{PROMPT}}: the prompt.
 * - {{SIZE}}: e.g. "1536x1024", from the provider's default values
 *   SIZE_SQUARE, SIZE_LANDSCAPE and SIZE_PORTRAIT, or else OpenAI's sizes.
 * - {{RATIO}}: e.g. "16:9", from RATIO_SQUARE, RATIO_LANDSCAPE and
 *   RATIO_PORTRAIT, or else 1:1, 16:9 and 9:16.
 * - {{QUALITY}}: from QUALITY_STANDARD and QUALITY_HD, or else the quality.
 * - {{STYLE}}: the style, or the provider's default STYLE.
 *
 * The return path leads to base64 image data, or to a URL of the image.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_image extends base {
    /** The longest prompt. DALL-E 3 allows 4000 characters. */
    const MAX_PROMPT_LENGTH = 4000;

    /** The aspect ratios. */
    const ASPECT_RATIOS = ['square', 'landscape', 'portrait'];

    /** The qualities. */
    const QUALITIES = ['standard', 'hd'];

    /** Sizes, if the provider does not give its own. */
    const DEFAULT_SIZES = ['square' => '1024x1024', 'landscape' => '1536x1024', 'portrait' => '1024x1536'];

    /** Ratios, if the provider does not give its own. */
    const DEFAULT_RATIOS = ['square' => '1:1', 'landscape' => '16:9', 'portrait' => '9:16'];

    /** @var string the prompt. */
    protected $prompt;

    /** @var string square, landscape or portrait. */
    protected $aspectratio;

    /** @var string standard or hd. */
    protected $quality;

    /** @var string the style, e.g. vivid or natural, or '' for the provider's default. */
    protected $style;

    /**
     * Create the action.
     *
     * @param int $contextid where the request is made.
     * @param int $userid who makes the request.
     * @param string $prompt a description of the image.
     * @param string $aspectratio square, landscape or portrait.
     * @param string $quality standard or hd.
     * @param string $style e.g. vivid or natural, or '' for the provider's default.
     */
    public function __construct(
        int $contextid,
        int $userid,
        string $prompt,
        string $aspectratio = 'square',
        string $quality = 'standard',
        string $style = ''
    ) {
        parent::__construct($contextid, $userid);
        $this->prompt = trim($prompt);
        $this->aspectratio = in_array($aspectratio, self::ASPECT_RATIOS) ? $aspectratio : 'square';
        $this->quality = in_array($quality, self::QUALITIES) ? $quality : 'standard';
        $this->style = trim($style);
    }

    #[\Override]
    public function get_capabilities(): array {
        return [provider::CAPABILITY_IMAGE];
    }

    #[\Override]
    public function validate(): void {
        if ($this->prompt === '') {
            throw new \moodle_exception('errornoprompt', 'tool_aibridge');
        }
        if (\core_text::strlen($this->prompt) > self::MAX_PROMPT_LENGTH) {
            throw new \moodle_exception('errortexttoolong', 'tool_aibridge', '', self::MAX_PROMPT_LENGTH);
        }
    }

    #[\Override]
    public function get_tokens(provider $provider): array {
        $defaults = $provider->get_token_defaults();
        $key = strtoupper($this->aspectratio);
        $tokens = [
            'PROMPT' => $this->prompt,
            'ASPECTRATIO' => $this->aspectratio,
            'SIZE' => isset($defaults['SIZE_' . $key]) ? $defaults['SIZE_' . $key] : self::DEFAULT_SIZES[$this->aspectratio],
            'RATIO' => isset($defaults['RATIO_' . $key]) ? $defaults['RATIO_' . $key] : self::DEFAULT_RATIOS[$this->aspectratio],
        ];
        $key = 'QUALITY_' . strtoupper($this->quality);
        $tokens['QUALITY'] = isset($defaults[$key]) ? $defaults[$key] : $this->quality;
        if ($this->style !== '') {
            $tokens['STYLE'] = $this->style;
        }
        return array_merge($this->tokens, $tokens);
    }

    #[\Override]
    public function parse_reply(string $reply, array $info, provider $provider): response {
        $record = $provider->get_record();
        $returnpath = trim((string) $record->returnpath);
        $revised = '';
        if ($returnpath === '') {
            // The reply is the image itself.
            $data = $reply;
        } else {
            $decoded = json_decode($reply, true);
            if (!is_array($decoded)) {
                throw new \moodle_exception('errorreplynotjson', 'tool_aibridge', '', $record->name);
            }
            $value = caller::extract_by_path($decoded, $returnpath);
            if (!is_string($value) || $value === '') {
                throw new \moodle_exception('errornotimage', 'tool_aibridge');
            }
            if (preg_match('#^https?://#i', $value)) {
                $data = image::download($value);
            } else {
                $data = base64_decode($value, true);
                if ($data === false) {
                    throw new \moodle_exception('errornotimage', 'tool_aibridge');
                }
            }
            // DALL-E 3 rewrites the prompt, and says how.
            $keys = explode(',', $returnpath);
            array_pop($keys);
            $parent = $keys ? caller::extract_by_path($decoded, implode(',', $keys)) : $decoded;
            if (is_array($parent) && isset($parent['revised_prompt']) && is_string($parent['revised_prompt'])) {
                $revised = $parent['revised_prompt'];
            }
        }
        [$extension, $mimetype] = image::identify($data);
        return response::success(response::ROUTE_EXTERNAL, $provider->get_id(), $provider->get_name(), (string) $record->model)
            ->set_image($data, $mimetype, $extension)
            ->set_content($revised);
    }

    #[\Override]
    public function get_prompt_for_log(): string {
        return $this->prompt;
    }

    #[\Override]
    public function get_reply_for_log(response $response): string {
        return $response->get_mimetype() . ' (' . display_size(strlen($response->get_image())) . ')';
    }

    #[\Override]
    public function get_core_class(): ?string {
        return '\\core_ai\\aiactions\\generate_image';
    }

    #[\Override]
    public function create_core_action() {
        $class = $this->get_core_class();
        $style = ($this->style === '') ? 'vivid' : $this->style;
        return new $class($this->contextid, $this->userid, $this->prompt, $this->quality, $this->aspectratio, 1, $style);
    }

    #[\Override]
    public function parse_core_response($coreresponse): response {
        $name = get_string('coreai', 'tool_aibridge');
        if (!$coreresponse->get_success()) {
            $error = $coreresponse->get_errormessage();
            if (method_exists($coreresponse, 'get_error') && $coreresponse->get_error() !== $error) {
                $error = trim($coreresponse->get_error() . ' ' . $error);
            }
            return response::failure('core', $error, response::ROUTE_CORE, 0, $name);
        }
        $data = $coreresponse->get_response_data();
        if (empty($data['draftfile'])) {
            return response::failure('notimage', '', response::ROUTE_CORE, 0, $name);
        }
        $content = $data['draftfile']->get_content();
        [$extension, $mimetype] = image::identify($content);
        $model = isset($data['model']) ? (string) $data['model'] : '';
        return response::success(response::ROUTE_CORE, 0, $name, $model)
            ->set_image($content, $mimetype, $extension)
            ->set_content(isset($data['revisedprompt']) ? (string) $data['revisedprompt'] : '');
    }
}
