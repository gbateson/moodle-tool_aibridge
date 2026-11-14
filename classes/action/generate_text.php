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
use tool_aibridge\provider;
use tool_aibridge\response;

/**
 * Generate text from a prompt, and optionally from files such as images or audio.
 *
 * Request template tokens: {{PROMPT}}, {{SYSTEM}}, and any token set with
 * set_token(), e.g. {{MAXTOKENS}}. If the template has no {{SYSTEM}}, the
 * system instructions are put before the prompt.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_text extends base {
    /** @var string the prompt. */
    protected $prompt;

    /** @var string the system instructions, e.g. "You are a teacher of English." */
    protected $system;

    /** @var \stored_file[] the files to send. */
    protected $files;

    /**
     * Create the action.
     *
     * @param int $contextid where the request is made.
     * @param int $userid who makes the request.
     * @param string $prompt
     * @param string $system the system instructions.
     * @param \stored_file[] $files files to send to a multimodal provider.
     */
    public function __construct(int $contextid, int $userid, string $prompt, string $system = '', array $files = []) {
        parent::__construct($contextid, $userid);
        $this->prompt = $prompt;
        $this->system = $system;
        $this->files = array_values($files);
    }

    /**
     * Get the prompt.
     *
     * @return string
     */
    public function get_prompt(): string {
        return $this->prompt;
    }

    /**
     * Get the prompt with the system instructions before it.
     *
     * @return string
     */
    public function get_full_prompt(): string {
        if (trim($this->system) === '') {
            return $this->prompt;
        }
        return trim($this->system) . "\n\n" . $this->prompt;
    }

    #[\Override]
    public function get_files(): array {
        return $this->files;
    }

    #[\Override]
    public function get_capabilities(): array {
        if ($this->files) {
            return [provider::CAPABILITY_MULTIMODAL];
        }
        return [provider::CAPABILITY_TEXT, provider::CAPABILITY_MULTIMODAL];
    }

    #[\Override]
    public function validate(): void {
        if (trim($this->prompt) === '') {
            throw new \moodle_exception('errornoprompt', 'tool_aibridge');
        }
    }

    #[\Override]
    public function get_tokens(provider $provider): array {
        if (strpos((string) $provider->get_record()->requesttemplate, '{{SYSTEM}}') === false) {
            $tokens = ['PROMPT' => $this->get_full_prompt()];
        } else {
            $tokens = ['PROMPT' => $this->prompt, 'SYSTEM' => $this->system];
        }
        return array_merge($this->tokens, $tokens);
    }

    #[\Override]
    public function parse_reply(string $reply, array $info, provider $provider): response {
        $record = $provider->get_record();
        $decoded = json_decode($reply, true);
        if (!is_array($decoded)) {
            throw new \moodle_exception('errorreplynotjson', 'tool_aibridge', '', $record->name);
        }
        $content = caller::extract_by_path($decoded, $record->returnpath);
        if (!is_scalar($content)) {
            throw new \moodle_exception('errorreplynotext', 'tool_aibridge', '', $record->name);
        }
        [$prompttokens, $completiontokens] = caller::find_token_usage($decoded);
        $model = isset($decoded['model']) && is_string($decoded['model']) ? $decoded['model'] : (string) $record->model;
        return response::success(response::ROUTE_EXTERNAL, $provider->get_id(), $provider->get_name(), $model)
                ->set_content((string) $content)
                ->set_token_usage($prompttokens, $completiontokens);
    }

    #[\Override]
    public function get_prompt_for_log(): string {
        return $this->get_full_prompt();
    }

    #[\Override]
    public function get_core_class(): ?string {
        if ($this->files) {
            return null;
        }
        return '\\core_ai\\aiactions\\generate_text';
    }

    #[\Override]
    public function create_core_action() {
        $class = $this->get_core_class();
        return new $class($this->contextid, $this->userid, $this->get_full_prompt());
    }

    #[\Override]
    public function parse_core_response($coreresponse): response {
        $name = get_string('coreai', 'tool_aibridge');
        $model = method_exists($coreresponse, 'get_model_used') ? (string) $coreresponse->get_model_used() : '';
        if (!$coreresponse->get_success()) {
            // Moodle 4.5 has only an error message; Moodle 5.0 and later also have an error.
            $error = $coreresponse->get_errormessage();
            if (method_exists($coreresponse, 'get_error') && $coreresponse->get_error() !== $error) {
                $error = trim($coreresponse->get_error() . ' ' . $error);
            }
            return response::failure('core', $error, response::ROUTE_CORE, 0, $name)
                ->set_httpcode((int) $coreresponse->get_errorcode());
        }
        $data = $coreresponse->get_response_data();
        $prompttokens = isset($data['prompttokens']) ? (int) $data['prompttokens'] : null;
        $completiontokens = isset($data['completiontokens']) ? (int) $data['completiontokens'] : null;
        return response::success(response::ROUTE_CORE, 0, $name, $model)
            ->set_content((string) ($data['generatedcontent'] ?? ''))
            ->set_token_usage($prompttokens, $completiontokens);
    }
}
