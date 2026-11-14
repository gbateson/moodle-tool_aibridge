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

use tool_aibridge\action\base;

/**
 * Log each request sent to an AI provider.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class logger {
    /** The table of requests. */
    const TABLE = 'tool_aibridge_log';

    /**
     * Log a request, and set the log id in its response.
     *
     * @param base $action
     * @param response $response
     * @param int $timecreated when the request was sent.
     * @return int the id of the log record.
     */
    public static function log(base $action, response $response, int $timecreated): int {
        global $DB;
        $record = (object) [
            'userid' => $action->get_userid(),
            'contextid' => $action->get_contextid(),
            'component' => \core_text::substr($action->get_component(), 0, 100),
            'purpose' => \core_text::substr($action->get_purpose(), 0, 100),
            'action' => $action->get_name(),
            'route' => $response->get_route() ? $response->get_route() : response::ROUTE_EXTERNAL,
            'providerid' => $response->get_providerid(),
            'providername' => \core_text::substr($response->get_providername(), 0, 255),
            'model' => \core_text::substr($response->get_model(), 0, 255),
            'success' => $response->get_success() ? 1 : 0,
            'errorcode' => \core_text::substr($response->get_errorcode(), 0, 100),
            'error' => $response->get_error(),
            'prompttokens' => $response->get_prompttokens(),
            'completiontokens' => $response->get_completiontokens(),
            'prompt' => null,
            'response' => null,
            'timecreated' => $timecreated,
            'timecompleted' => time(),
        ];
        if (get_config('tool_aibridge', 'logprompts')) {
            $record->prompt = $action->get_prompt_for_log();
        }
        if (get_config('tool_aibridge', 'logresponses') && $response->get_success()) {
            $record->response = $action->get_reply_for_log($response);
        }
        $id = $DB->insert_record(self::TABLE, $record);
        $response->set_logid($id);
        return $id;
    }

    /**
     * Delete old log records, and the prompts and replies of records that should no longer keep them.
     *
     * @param int|null $now
     */
    public static function cleanup(?int $now = null): void {
        global $DB;
        if ($now === null) {
            $now = time();
        }
        $days = (int) get_config('tool_aibridge', 'logretention');
        if ($days > 0) {
            $DB->delete_records_select(self::TABLE, 'timecreated < :before', ['before' => $now - ($days * DAYSECS)]);
        }
        $days = (int) get_config('tool_aibridge', 'contentretention');
        if ($days > 0) {
            $DB->set_field_select(self::TABLE, 'prompt', null, 'timecreated < :before', ['before' => $now - ($days * DAYSECS)]);
            $DB->set_field_select(self::TABLE, 'response', null, 'timecreated < :before', ['before' => $now - ($days * DAYSECS)]);
        }
    }
}
