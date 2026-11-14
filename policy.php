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
 * Show the AI policy, and where data goes, and let the user accept the policy.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_aibridge\policy;

$contextid = optional_param('contextid', SYSCONTEXTID, PARAM_INT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$accept = optional_param('accept', 0, PARAM_BOOL);

$context = context::instance_by_id($contextid);
[$context, $course, $cm] = get_context_info_array($context->id);
require_login($course, false, $cm);
if (isguestuser()) {
    throw new require_login_exception('noguest');
}

$url = new moodle_url('/admin/tool/aibridge/policy.php', ['contextid' => $contextid]);
if ($returnurl) {
    $url->param('returnurl', $returnurl);
}
$PAGE->set_url($url);
if (!$course) {
    $PAGE->set_context($context);
}
$title = get_string('policy', 'tool_aibridge');
$PAGE->set_title($title);
$PAGE->set_heading($course ? format_string($course->fullname) : $title);

$next = $returnurl ? new moodle_url($returnurl) : new moodle_url('/');
if ($accept && confirm_sesskey()) {
    policy::accept($USER->id, $context->id);
    redirect($next);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo $OUTPUT->box(policy::get_text(), 'generalbox');

$notices = policy::get_data_notices($context, $USER->id);
if ($notices) {
    echo $OUTPUT->heading(get_string('wheredatagoes', 'tool_aibridge'), 3);
    echo html_writer::alist(array_map('s', $notices));
}

if (policy::has_accepted($USER->id)) {
    echo $OUTPUT->notification(get_string('policyaccepted', 'tool_aibridge'), 'success');
    echo $OUTPUT->continue_button($next);
} else {
    $accepturl = new moodle_url($url, ['accept' => 1, 'sesskey' => sesskey()]);
    // Moodle 4.2 and later give buttons a type; earlier versions say whether they are primary.
    $primary = defined('single_button::BUTTON_PRIMARY') ? single_button::BUTTON_PRIMARY : true;
    echo $OUTPUT->confirm(
        get_string('policyconfirm', 'tool_aibridge'),
        new single_button($accepturl, get_string('policyaccept', 'tool_aibridge'), 'post', $primary),
        new single_button($next, get_string('cancel'), 'get')
    );
}
echo $OUTPUT->footer();
