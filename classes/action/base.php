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

use tool_aibridge\provider;
use tool_aibridge\response;

/**
 * An AI action, e.g. generate text or generate speech.
 *
 * An action knows which providers can do it, what tokens to put in a
 * provider's request template, and how to read the reply. If Moodle's own
 * AI subsystem can do it, the action also knows how to ask Moodle.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /** @var int the id of the context in which the request is made. */
    protected $contextid;

    /** @var int the id of the user who makes the request. */
    protected $userid;

    /** @var string the plugin that makes the request, e.g. "mod_vocab". */
    protected $component = '';

    /** @var string what the plugin makes the request for, e.g. "questions". */
    protected $purpose = '';

    /** @var array extra tokens for the request template, e.g. MAXTOKENS. */
    protected $tokens = [];

    /**
     * Create the action.
     *
     * @param int $contextid where the request is made.
     * @param int $userid who makes the request.
     */
    public function __construct(int $contextid, int $userid) {
        $this->contextid = $contextid;
        $this->userid = $userid;
    }

    /**
     * Get the name of the action, e.g. "generate_text".
     *
     * @return string
     */
    public function get_name(): string {
        $parts = explode('\\', get_class($this));
        return end($parts);
    }

    /**
     * Say which plugin makes the request, and why. This is shown in the log.
     *
     * @param string $component e.g. "mod_vocab".
     * @param string $purpose e.g. "questions".
     * @return base
     */
    public function set_purpose(string $component, string $purpose = ''): base {
        $this->component = $component;
        $this->purpose = $purpose;
        return $this;
    }

    /**
     * Set an extra token for the request template, e.g. MAXTOKENS or TEMPERATURE.
     *
     * @param string $name
     * @param string $value
     * @return base
     */
    public function set_token(string $name, string $value): base {
        $this->tokens[strtoupper($name)] = $value;
        return $this;
    }

    /**
     * Get the context in which the request is made.
     *
     * @return \context
     */
    public function get_context(): \context {
        return \context::instance_by_id($this->contextid);
    }

    /**
     * Get the id of the context in which the request is made.
     *
     * @return int
     */
    public function get_contextid(): int {
        return $this->contextid;
    }

    /**
     * Get the id of the user who makes the request.
     *
     * @return int
     */
    public function get_userid(): int {
        return $this->userid;
    }

    /**
     * Get the plugin that makes the request.
     *
     * @return string
     */
    public function get_component(): string {
        return $this->component;
    }

    /**
     * Get what the plugin makes the request for.
     *
     * @return string
     */
    public function get_purpose(): string {
        return $this->purpose;
    }

    /**
     * Get the files to send with the request.
     *
     * @return \stored_file[]
     */
    public function get_files(): array {
        return [];
    }

    /**
     * Get the provider capabilities that can do this action, any one of them.
     *
     * @return string[]
     */
    abstract public function get_capabilities(): array;

    /**
     * Check that the action can be sent.
     *
     * @throws \moodle_exception if it cannot.
     */
    public function validate(): void {
    }

    /**
     * Get the tokens for a provider's request template.
     *
     * @param provider $provider
     * @return array token name => value.
     */
    abstract public function get_tokens(provider $provider): array;

    /**
     * Read a provider's reply.
     *
     * @param string $reply the raw reply.
     * @param array $info the curl info, e.g. content_type.
     * @param provider $provider
     * @return response
     * @throws \moodle_exception if the reply cannot be read.
     */
    abstract public function parse_reply(string $reply, array $info, provider $provider): response;

    /**
     * Get the prompt, to keep in the log if the site keeps prompts.
     *
     * @return string
     */
    abstract public function get_prompt_for_log(): string;

    /**
     * Get the reply, to keep in the log if the site keeps replies.
     *
     * @param response $response
     * @return string
     */
    public function get_reply_for_log(response $response): string {
        return $response->get_content();
    }

    /**
     * Get the class of the matching action in Moodle's own AI subsystem.
     *
     * @return string|null the class name, or null if Moodle has no matching action.
     */
    public function get_core_class(): ?string {
        return null;
    }

    /**
     * Create the matching action for Moodle's own AI subsystem.
     *
     * @return object|null
     */
    public function create_core_action() {
        return null;
    }

    /**
     * Read the response from Moodle's own AI subsystem.
     *
     * @param object $coreresponse a \core_ai\aiactions\responses\response_base.
     * @return response
     */
    public function parse_core_response($coreresponse): response {
        return response::failure('nocore', '', response::ROUTE_CORE);
    }
}
