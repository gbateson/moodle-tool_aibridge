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
 * An external AI provider: one endpoint, with its URL, headers, key and request template.
 *
 * A provider belongs to the user who added it, and is shared in one context:
 * the whole site, a course category, a course, or only its owner (the
 * owner's user context). The API key is encrypted, and is never sent to
 * the browser, exported or shown in error messages.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider {
    /** The provider generates text from text. */
    const CAPABILITY_TEXT = 'text';

    /** The provider generates text from text and files, e.g. images or audio. */
    const CAPABILITY_MULTIMODAL = 'multimodal';

    /** The provider generates speech from text. */
    const CAPABILITY_SPEECH = 'speech';

    /** The provider generates images from text. */
    const CAPABILITY_IMAGE = 'image';

    /** Files are not sent. */
    const UPLOAD_NONE = 'none';

    /** Files are sent inline, as base64. */
    const UPLOAD_INLINE_BASE64 = 'inline_base64';

    /** The table of providers. */
    const TABLE = 'tool_aibridge_providers';

    /** @var \stdClass the record from the providers table. */
    protected $record;

    /**
     * Create a provider from its record.
     *
     * @param \stdClass $record
     */
    public function __construct(\stdClass $record) {
        $this->record = $record;
    }

    /**
     * Get a provider by its id.
     *
     * @param int $id
     * @return provider
     * @throws \dml_exception if there is no such provider.
     */
    public static function get(int $id): provider {
        global $DB;
        return new provider($DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST));
    }

    /**
     * Get the capabilities a provider may have.
     *
     * @return string[] capability => name.
     */
    public static function get_capability_options(): array {
        $options = [];
        $capabilities = [self::CAPABILITY_TEXT, self::CAPABILITY_MULTIMODAL, self::CAPABILITY_SPEECH, self::CAPABILITY_IMAGE];
        foreach ($capabilities as $capability) {
            $options[$capability] = get_string('capability_' . $capability, 'tool_aibridge');
        }
        return $options;
    }

    /**
     * Get the provider's record. The API key in it is encrypted.
     *
     * @return \stdClass
     */
    public function get_record(): \stdClass {
        return $this->record;
    }

    /**
     * Get the provider's id.
     *
     * @return int
     */
    public function get_id(): int {
        return (int) $this->record->id;
    }

    /**
     * Get the provider's name.
     *
     * @return string
     */
    public function get_name(): string {
        return (string) $this->record->name;
    }

    /**
     * Get the provider's capability: text, multimodal or speech.
     *
     * @return string
     */
    public function get_capability(): string {
        return (string) $this->record->capability;
    }

    /**
     * Get the context in which the provider is shared.
     *
     * @return \context
     */
    public function get_context(): \context {
        return \context::instance_by_id($this->record->contextid);
    }

    /**
     * Is this provider only for its owner?
     *
     * @return bool
     */
    public function is_personal(): bool {
        return ($this->get_context()->contextlevel == CONTEXT_USER);
    }

    /**
     * Does the provider have an API key?
     *
     * @return bool
     */
    public function has_apikey(): bool {
        return (trim((string) $this->record->apikey) !== '');
    }

    /**
     * Get the provider's API key, decrypted.
     *
     * @return string
     */
    public function get_apikey(): string {
        return self::decrypt_key((string) $this->record->apikey);
    }

    /**
     * Encrypt an API key, if the site can.
     *
     * @param string $key
     * @return string
     */
    public static function encrypt_key(string $key): string {
        if ($key === '' || !class_exists('\core\encryption')) {
            return $key;
        }
        return \core\encryption::encrypt($key);
    }

    /**
     * Decrypt an API key.
     *
     * @param string $key
     * @return string the key, or '' if it cannot be decrypted, e.g. after the site's encryption key changed.
     */
    public static function decrypt_key(string $key): string {
        if ($key === '' || !class_exists('\core\encryption')) {
            return $key;
        }
        try {
            return \core\encryption::decrypt($key);
        } catch (\moodle_exception $e) {
            return '';
        }
    }

    /**
     * Get the provider's default token values, e.g. VOICE or MAXTOKENS.
     *
     * @return array token name => value.
     */
    public function get_token_defaults(): array {
        $defaults = json_decode((string) $this->record->tokendefaults, true);
        if (!is_array($defaults)) {
            return [];
        }
        $tokens = [];
        foreach ($defaults as $name => $value) {
            if (is_scalar($value)) {
                $tokens[strtoupper($name)] = (string) $value;
            }
        }
        return $tokens;
    }

    /**
     * Get the host that data is sent to, e.g. "api.openai.com".
     *
     * @return string
     */
    public function get_host(): string {
        $host = parse_url((string) $this->record->url, PHP_URL_HOST);
        return $host ? $host : '';
    }

    /**
     * Get what users are told about where their data goes.
     *
     * @return string plain text.
     */
    public function get_data_notice(): string {
        $notice = trim((string) $this->record->datanotice);
        if ($notice === '') {
            $notice = get_string(
                'defaultdatanotice',
                'tool_aibridge',
                ['name' => $this->get_name(), 'host' => $this->get_host()]
            );
        }
        return $notice;
    }

    /**
     * Is the provider enabled, and shared at this time?
     *
     * @param int|null $time
     * @return bool
     */
    public function is_active(?int $time = null): bool {
        return sharing::is_active($this->record, $time);
    }

    /**
     * Can a user send requests to this provider from a context?
     *
     * @param \context $context where the request is made.
     * @param int $userid
     * @return bool
     */
    public function can_use(\context $context, int $userid): bool {
        return sharing::can_use(
            $this->record,
            $context,
            $userid,
            'tool/aibridge:useprovider',
            (bool) get_config('tool_aibridge', 'allowuserproviders')
        );
    }

    /**
     * Can a user change or delete this provider?
     *
     * @param int $userid
     * @return bool
     */
    public function can_edit(int $userid): bool {
        if (has_capability('tool/aibridge:manageproviders', \context_system::instance(), $userid)) {
            return true;
        }
        return ($this->record->ownerid == $userid);
    }

    /**
     * Get the providers a user can send requests to from a context.
     * The user's own providers come first, then those shared nearest to the context.
     *
     * @param \context $context where the request is made.
     * @param string[] $capabilities the capabilities the provider must have, any one of them.
     * @param int $userid
     * @return provider[] provider id => provider.
     */
    public static function get_available(\context $context, array $capabilities, int $userid): array {
        global $DB;
        if (empty($capabilities)) {
            return [];
        }
        [$capsql, $params] = $DB->get_in_or_equal($capabilities, SQL_PARAMS_NAMED, 'cap');
        $contextids = $context->get_parent_context_ids(true);
        $contextids[] = \context_user::instance($userid)->id;
        [$ctxsql, $ctxparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx');
        $sql = "SELECT p.*, ctx.depth AS contextdepth
                   FROM {" . self::TABLE . "} p
                   JOIN {context} ctx ON ctx.id = p.contextid
                  WHERE p.enabled = 1 AND p.capability $capsql
                    AND (p.ownerid = :ownerid OR p.contextid $ctxsql)";
        $params = array_merge($params, $ctxparams, ['ownerid' => $userid]);
        $providers = [];
        foreach ($DB->get_records_sql($sql, $params) as $record) {
            $provider = new provider($record);
            if ($provider->can_use($context, $userid)) {
                $providers[$record->id] = $provider;
            }
        }
        uasort($providers, function ($a, $b) use ($userid) {
            $a = $a->get_record();
            $b = $b->get_record();
            $aown = ($a->ownerid == $userid) ? 0 : 1;
            $bown = ($b->ownerid == $userid) ? 0 : 1;
            if ($aown != $bown) {
                return $aown - $bown;
            }
            if ($a->contextdepth != $b->contextdepth) {
                return $b->contextdepth - $a->contextdepth;
            }
            if ($a->sortorder != $b->sortorder) {
                return $a->sortorder - $b->sortorder;
            }
            return $a->id - $b->id;
        });
        return $providers;
    }

    /**
     * Get the providers to show on the management page for a context: those
     * shared in the context or its parents, the user's own, and every provider
     * if the user manages providers and the context is the site.
     *
     * @param \context $context
     * @param int $userid
     * @return provider[] provider id => provider.
     */
    public static function get_manageable(\context $context, int $userid): array {
        global $DB;
        $params = ['ownerid' => $userid];
        if (
            $context->contextlevel == CONTEXT_SYSTEM
                && has_capability('tool/aibridge:manageproviders', $context, $userid)
        ) {
            $where = '1 = 1';
        } else {
            [$ctxsql, $ctxparams] = $DB->get_in_or_equal($context->get_parent_context_ids(true), SQL_PARAMS_NAMED, 'ctx');
            $where = "p.ownerid = :ownerid OR p.contextid $ctxsql";
            $params = array_merge($params, $ctxparams);
        }
        $sql = "SELECT p.*
                   FROM {" . self::TABLE . "} p
                  WHERE $where
               ORDER BY p.capability, p.sortorder, p.name, p.id";
        $providers = [];
        foreach ($DB->get_records_sql($sql, $params) as $record) {
            $providers[$record->id] = new provider($record);
        }
        return $providers;
    }

    /**
     * Get the contexts in which a user may share a provider, from a context.
     *
     * @param \context $context the context of the management page.
     * @param int $userid
     * @return string[] context id => name.
     */
    public static function get_sharing_options(\context $context, int $userid): array {
        return sharing::get_options(
            $context,
            $userid,
            'tool/aibridge:addprovider',
            'tool/aibridge:manageproviders',
            (bool) get_config('tool_aibridge', 'allowuserproviders')
        );
    }

    /**
     * Add or update a provider.
     *
     * @param \stdClass $data the provider's fields. If it has an id, the provider is updated.
     * @param string|null $apikey a new API key, '' to remove the key, or null to keep the key.
     * @return provider
     */
    public static function save(\stdClass $data, ?string $apikey = null): provider {
        global $DB;
        $record = clone($data);
        $record->timemodified = time();
        if ($apikey !== null) {
            $record->apikey = self::encrypt_key(trim($apikey));
        } else {
            unset($record->apikey);
        }
        if (empty($record->id)) {
            $record->timecreated = $record->timemodified;
            $record->id = $DB->insert_record(self::TABLE, $record);
        } else {
            $DB->update_record(self::TABLE, $record);
        }
        return self::get($record->id);
    }

    /**
     * Delete the provider. Its log records are kept.
     */
    public function delete(): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['id' => $this->get_id()]);
    }
}
