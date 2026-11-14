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

/**
 * The log of requests sent to AI providers.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once($CFG->libdir . '/tablelib.php');

$contextid = optional_param('contextid', SYSCONTEXTID, PARAM_INT);
$context = context::instance_by_id($contextid);
require_login();
$title = get_string('log', 'tool_aibridge');
tool_aibridge_setup_page(
    '/admin/tool/aibridge/log.php',
    ['contextid' => $contextid],
    $context,
    'tool/aibridge:viewlog',
    'tool_aibridge_log',
    $title
);

echo $OUTPUT->header();
echo $OUTPUT->heading($title);

$columns = [
    'timecreated' => get_string('time'),
    'fullname' => get_string('user'),
    'contextid' => get_string('context', 'role'),
    'component' => get_string('component', 'tool_aibridge'),
    'action' => get_string('action'),
    'providername' => get_string('provider', 'tool_aibridge'),
    'model' => get_string('model', 'tool_aibridge'),
    'tokens' => get_string('tokens', 'tool_aibridge'),
    'success' => get_string('result', 'tool_aibridge'),
];
$table = new flexible_table('tool_aibridge_log');
$table->define_columns(array_keys($columns));
$table->define_headers(array_values($columns));
$table->define_baseurl($PAGE->url);
$table->sortable(true, 'timecreated', SORT_DESC);
$table->no_sorting('tokens');
$table->no_sorting('contextid');
$table->set_attribute('class', 'generaltable');
$table->pagesize(50, 0);
$table->setup();

$userfields = class_exists('\core_user\fields')
        ? \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects
        : get_all_user_name_fields(true, 'u');
$where = '1 = 1';
$params = [];
if ($context->contextlevel != CONTEXT_SYSTEM) {
    $where = '(ctx.id = :contextid OR ' . $DB->sql_like('ctx.path', ':path') . ')';
    $params = ['contextid' => $context->id, 'path' => $context->path . '/%'];
}
$from = '{tool_aibridge_log} l
          LEFT JOIN {user} u ON u.id = l.userid
          LEFT JOIN {context} ctx ON ctx.id = l.contextid';
$table->pagesize(50, $DB->count_records_sql("SELECT COUNT(1) FROM $from WHERE $where", $params));
$sort = $table->get_sql_sort();
$sort = preg_replace('/\b(timecreated|component|action|providername|model|success)\b/', 'l.$1', $sort);
$sort = str_replace('fullname', 'u.lastname', $sort);
if ($sort === '') {
    $sort = 'l.timecreated DESC';
}
$records = $DB->get_records_sql(
    "SELECT l.*, $userfields FROM $from WHERE $where ORDER BY $sort",
    $params,
    $table->get_page_start(),
    $table->get_page_size()
);

foreach ($records as $record) {
    $recordcontext = context::instance_by_id($record->contextid, IGNORE_MISSING);
    $tokens = '';
    if ($record->prompttokens !== null || $record->completiontokens !== null) {
        $tokens = (int) $record->prompttokens . ' / ' . (int) $record->completiontokens;
    }
    if ($record->success) {
        $result = get_string('success');
    } else {
        $result = html_writer::tag('span', s($record->errorcode), ['class' => 'text-danger', 'title' => $record->error]);
    }
    $table->add_data([
        userdate($record->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
        fullname($record),
        $recordcontext ? $recordcontext->get_context_name(false, true) : '-',
        s(trim($record->component . ' ' . $record->purpose)),
        s($record->action),
        s($record->providername),
        s($record->model),
        $tokens,
        $result,
    ]);
}
$table->finish_output();
echo $OUTPUT->footer();
