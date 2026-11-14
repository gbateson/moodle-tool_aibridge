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

namespace tool_aibridge\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for the AI bridge.
 *
 * The bridge keeps: a log of each request (with the prompt and reply only
 * if the site keeps them), when each user accepted the AI policy, and the
 * providers that users added. API keys are never exported.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the data that the bridge keeps and sends.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('tool_aibridge_log', [
            'userid' => 'privacy:metadata:log:userid',
            'contextid' => 'privacy:metadata:log:contextid',
            'component' => 'privacy:metadata:log:component',
            'action' => 'privacy:metadata:log:action',
            'providername' => 'privacy:metadata:log:providername',
            'model' => 'privacy:metadata:log:model',
            'templateid' => 'privacy:metadata:log:templateid',
            'prompt' => 'privacy:metadata:log:prompt',
            'response' => 'privacy:metadata:log:response',
            'timecreated' => 'privacy:metadata:log:timecreated',
        ], 'privacy:metadata:log');
        $collection->add_database_table('tool_aibridge_policy', [
            'userid' => 'privacy:metadata:policy:userid',
            'contextid' => 'privacy:metadata:policy:contextid',
            'timeaccepted' => 'privacy:metadata:policy:timeaccepted',
        ], 'privacy:metadata:policy');
        $collection->add_database_table('tool_aibridge_providers', [
            'ownerid' => 'privacy:metadata:providers:ownerid',
            'name' => 'privacy:metadata:providers:name',
            'url' => 'privacy:metadata:providers:url',
            'apikey' => 'privacy:metadata:providers:apikey',
            'timecreated' => 'privacy:metadata:providers:timecreated',
        ], 'privacy:metadata:providers');
        $collection->add_database_table('tool_aibridge_templates', [
            'ownerid' => 'privacy:metadata:templates:ownerid',
            'name' => 'privacy:metadata:templates:name',
            'body' => 'privacy:metadata:templates:body',
            'timecreated' => 'privacy:metadata:templates:timecreated',
        ], 'privacy:metadata:templates');
        $collection->add_database_table('tool_aibridge_tpl_versions', [
            'userid' => 'privacy:metadata:tplversions:userid',
            'timecreated' => 'privacy:metadata:tplversions:timecreated',
        ], 'privacy:metadata:tplversions');
        $collection->add_external_location_link('aiprovider', [
            'prompt' => 'privacy:metadata:aiprovider:prompt',
            'files' => 'privacy:metadata:aiprovider:files',
        ], 'privacy:metadata:aiprovider');
        return $collection;
    }

    /**
     * Get the contexts in which a user has data.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $params = ['userid' => $userid];
        $contextlist->add_from_sql('SELECT contextid FROM {tool_aibridge_log} WHERE userid = :userid', $params);
        $contextlist->add_from_sql('SELECT contextid FROM {tool_aibridge_providers} WHERE ownerid = :userid', $params);
        $contextlist->add_from_sql('SELECT contextid FROM {tool_aibridge_templates} WHERE ownerid = :userid', $params);
        $contextlist->add_from_sql('SELECT t.contextid
                                      FROM {tool_aibridge_templates} t
                                      JOIN {tool_aibridge_tpl_versions} v ON v.templateid = t.id
                                     WHERE v.userid = :userid', $params);
        $contextlist->add_user_context($userid);
        return $contextlist;
    }

    /**
     * Get the users who have data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        $params = ['contextid' => $context->id];
        $userlist->add_from_sql('userid', 'SELECT userid FROM {tool_aibridge_log} WHERE contextid = :contextid', $params);
        $userlist->add_from_sql('ownerid', 'SELECT ownerid FROM {tool_aibridge_providers} WHERE contextid = :contextid', $params);
        $userlist->add_from_sql('ownerid', 'SELECT ownerid FROM {tool_aibridge_templates} WHERE contextid = :contextid', $params);
        $userlist->add_from_sql('userid', 'SELECT v.userid
                                              FROM {tool_aibridge_tpl_versions} v
                                              JOIN {tool_aibridge_templates} t ON t.id = v.templateid
                                             WHERE t.contextid = :contextid', $params);
        if ($context->contextlevel == CONTEXT_USER) {
            $params = ['userid' => $context->instanceid];
            $userlist->add_from_sql('userid', 'SELECT userid FROM {tool_aibridge_policy} WHERE userid = :userid', $params);
        }
    }

    /**
     * Export a user's data.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $subcontext = [get_string('pluginname', 'tool_aibridge')];
        foreach ($contextlist->get_contexts() as $context) {
            $requests = [];
            $params = ['userid' => $userid, 'contextid' => $context->id];
            foreach ($DB->get_records('tool_aibridge_log', $params, 'timecreated, id') as $log) {
                $requests[] = (object) [
                    'component' => $log->component,
                    'purpose' => $log->purpose,
                    'action' => $log->action,
                    'provider' => $log->providername,
                    'model' => $log->model,
                    'success' => transform::yesno($log->success),
                    'prompt' => $log->prompt,
                    'response' => $log->response,
                    'timecreated' => transform::datetime($log->timecreated),
                ];
            }
            if ($requests) {
                writer::with_context($context)->export_data(array_merge(
                    $subcontext,
                    [get_string('log', 'tool_aibridge')]
                ), (object) ['requests' => $requests]);
            }

            $providers = [];
            $params = ['ownerid' => $userid, 'contextid' => $context->id];
            foreach ($DB->get_records('tool_aibridge_providers', $params, 'id') as $provider) {
                // The API key is never exported.
                $providers[] = (object) [
                    'name' => $provider->name,
                    'capability' => $provider->capability,
                    'url' => $provider->url,
                    'model' => $provider->model,
                    'timecreated' => transform::datetime($provider->timecreated),
                    'timemodified' => transform::datetime($provider->timemodified),
                ];
            }
            if ($providers) {
                writer::with_context($context)->export_data(array_merge(
                    $subcontext,
                    [get_string('providers', 'tool_aibridge')]
                ), (object) ['providers' => $providers]);
            }

            $templates = [];
            $params = ['ownerid' => $userid, 'contextid' => $context->id];
            foreach ($DB->get_records('tool_aibridge_templates', $params, 'id') as $template) {
                $templates[] = (object) [
                    'shortname' => $template->shortname,
                    'type' => $template->type,
                    'name' => $template->name,
                    'body' => $template->body,
                    'version' => $template->version,
                    'timecreated' => transform::datetime($template->timecreated),
                    'timemodified' => transform::datetime($template->timemodified),
                ];
            }
            if ($templates) {
                writer::with_context($context)->export_data(array_merge(
                    $subcontext,
                    [get_string('library', 'tool_aibridge')]
                ), (object) ['templates' => $templates]);
            }

            if ($context->contextlevel == CONTEXT_USER && $context->instanceid == $userid) {
                if ($policy = $DB->get_record('tool_aibridge_policy', ['userid' => $userid])) {
                    writer::with_context($context)->export_data(
                        array_merge(
                            $subcontext,
                            [get_string('policy', 'tool_aibridge')]
                        ),
                        (object) ['timeaccepted' => transform::datetime($policy->timeaccepted)]
                    );
                }
            }
        }
    }

    /**
     * Delete users' templates in a context. A template that is shared is kept for the
     * people who use it, but its owner is removed. A template only for its owner is deleted.
     *
     * @param \context $context
     * @param int[]|null $userids the users, or null for every user.
     */
    protected static function delete_templates(\context $context, ?array $userids): void {
        global $DB;
        $params = ['contextid' => $context->id];
        $select = 'contextid = :contextid';
        if ($userids !== null) {
            if (empty($userids)) {
                return;
            }
            [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $select .= " AND ownerid $usersql";
            $params = array_merge($params, $userparams);
        }
        $templates = $DB->get_records_select('tool_aibridge_templates', $select, $params);
        foreach ($templates as $template) {
            if ($context->contextlevel == CONTEXT_USER) {
                $DB->delete_records('tool_aibridge_tpl_versions', ['templateid' => $template->id]);
                $DB->set_field('tool_aibridge_templates', 'parentid', 0, ['parentid' => $template->id]);
                $DB->delete_records('tool_aibridge_templates', ['id' => $template->id]);
            } else {
                $DB->set_field('tool_aibridge_templates', 'ownerid', 0, ['id' => $template->id]);
            }
        }
        // Remove the users from the versions of templates in this context.
        $sql = 'templateid IN (SELECT id FROM {tool_aibridge_templates} WHERE contextid = :contextid)';
        $params = ['contextid' => $context->id];
        if ($userids !== null) {
            [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $sql .= " AND userid $usersql";
            $params = array_merge($params, $userparams);
        }
        $DB->set_field_select('tool_aibridge_tpl_versions', 'userid', 0, $sql, $params);
    }

    /**
     * Delete the data of every user in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        $DB->delete_records('tool_aibridge_log', ['contextid' => $context->id]);
        $DB->delete_records('tool_aibridge_providers', ['contextid' => $context->id]);
        self::delete_templates($context, null);
        if ($context->contextlevel == CONTEXT_USER) {
            $DB->delete_records('tool_aibridge_policy', ['userid' => $context->instanceid]);
        }
    }

    /**
     * Delete a user's data in some contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $DB->delete_records('tool_aibridge_log', ['userid' => $userid, 'contextid' => $context->id]);
            $DB->delete_records('tool_aibridge_providers', ['ownerid' => $userid, 'contextid' => $context->id]);
            self::delete_templates($context, [$userid]);
            if ($context->contextlevel == CONTEXT_USER && $context->instanceid == $userid) {
                $DB->delete_records('tool_aibridge_policy', ['userid' => $userid]);
            }
        }
    }

    /**
     * Delete the data of some users in a context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$usersql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['contextid'] = $context->id;
        $DB->delete_records_select('tool_aibridge_log', "contextid = :contextid AND userid $usersql", $params);
        $DB->delete_records_select('tool_aibridge_providers', "contextid = :contextid AND ownerid $usersql", $params);
        self::delete_templates($context, $userids);
        if ($context->contextlevel == CONTEXT_USER && in_array($context->instanceid, $userids)) {
            $DB->delete_records('tool_aibridge_policy', ['userid' => $context->instanceid]);
        }
    }
}
