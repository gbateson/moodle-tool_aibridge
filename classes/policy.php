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
 * The AI policy, which users accept before their requests are sent to an AI provider.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class policy {
    /** The table of users who have accepted the policy. */
    const TABLE = 'tool_aibridge_policy';

    /** @var bool[] user id => has accepted, for this request. */
    protected static $accepted = [];

    /**
     * Must users accept the policy?
     *
     * @return bool
     */
    public static function is_required(): bool {
        $required = get_config('tool_aibridge', 'requirepolicy');
        return ($required === false || !empty($required));
    }

    /**
     * Has a user accepted the policy, or is there no need?
     *
     * @param int $userid
     * @return bool
     */
    public static function has_accepted(int $userid): bool {
        global $DB;
        if (!self::is_required()) {
            return true;
        }
        if (!isset(self::$accepted[$userid])) {
            self::$accepted[$userid] = $DB->record_exists(self::TABLE, ['userid' => $userid]);
        }
        return self::$accepted[$userid];
    }

    /**
     * Record that a user accepted the policy.
     *
     * @param int $userid
     * @param int $contextid where the policy was accepted.
     */
    public static function accept(int $userid, int $contextid): void {
        global $DB;
        if (!$DB->record_exists(self::TABLE, ['userid' => $userid])) {
            $DB->insert_record(self::TABLE, (object) [
                'userid' => $userid,
                'contextid' => $contextid,
                'timeaccepted' => time(),
            ]);
        }
        self::$accepted[$userid] = true;
    }

    /**
     * Forget that users accepted the policy, e.g. after the policy changed.
     *
     * @param int|null $userid one user, or null for every user.
     */
    public static function reset(?int $userid = null): void {
        global $DB;
        if ($userid === null) {
            $DB->delete_records(self::TABLE);
        } else {
            $DB->delete_records(self::TABLE, ['userid' => $userid]);
        }
        self::$accepted = [];
    }

    /**
     * Get the policy text.
     *
     * @return string HTML.
     */
    public static function get_text(): string {
        $text = trim((string) get_config('tool_aibridge', 'policytext'));
        if ($text === '') {
            $text = get_string('policydefault', 'tool_aibridge');
        }
        return format_text($text, FORMAT_HTML, ['context' => \context_system::instance()]);
    }

    /**
     * Get where data goes in a context: one notice for each AI provider a user can use there.
     *
     * @param \context $context
     * @param int $userid
     * @return string[] plain text.
     */
    public static function get_data_notices(\context $context, int $userid): array {
        $notices = [];
        if (core_bridge::is_installed() && get_config('tool_aibridge', 'coreroute') !== core_bridge::ROUTE_NEVER) {
            $names = core_bridge::get_provider_names();
            if ($names) {
                $notices[] = get_string('coredatanotice', 'tool_aibridge', implode(', ', $names));
            }
        }
        if (manager::is_external_allowed()) {
            $capabilities = array_keys(provider::get_capability_options());
            foreach (provider::get_available($context, $capabilities, $userid) as $provider) {
                $notices[] = $provider->get_data_notice();
            }
        }
        return array_unique($notices);
    }

    /**
     * Get the URL of the page where users accept the policy.
     *
     * @param \context $context
     * @param \moodle_url|null $returnurl where to go after accepting.
     * @return \moodle_url
     */
    public static function get_url(\context $context, ?\moodle_url $returnurl = null): \moodle_url {
        $params = ['contextid' => $context->id];
        // Only a page on this site can be returned to.
        if ($returnurl && strpos($returnurl->out(false), (new \moodle_url('/'))->out(false)) === 0) {
            $params['returnurl'] = $returnurl->out_as_local_url(false);
        }
        return new \moodle_url('/admin/tool/aibridge/policy.php', $params);
    }
}
