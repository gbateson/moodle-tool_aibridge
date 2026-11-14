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
 * Fine-tuning: send a training file to a provider, to make a tuned model.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/locallib.php');

use tool_aibridge\form\tuning_form;
use tool_aibridge\provider;
use tool_aibridge\tuning;

$contextid = optional_param('contextid', SYSCONTEXTID, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

$context = context::instance_by_id($contextid);
require_login();
$title = get_string('tunings', 'tool_aibridge');
tool_aibridge_setup_page(
    '/admin/tool/aibridge/tunings.php',
    ['contextid' => $contextid],
    $context,
    'tool/aibridge:addprovider',
    'tool_aibridge_tunings',
    $title
);
$baseurl = new moodle_url('/admin/tool/aibridge/tunings.php', ['contextid' => $contextid]);

if ($id && $action) {
    require_sesskey();
    $tuning = tuning::get($id);
    if (!$tuning->can_edit($USER->id)) {
        throw new required_capability_exception($context, 'tool/aibridge:manageproviders', 'nopermissions', '');
    }
    $name = $tuning->get_record()->name;
    switch ($action) {
        case 'check':
            $tuning->advance();
            redirect($baseurl);
            break;
        case 'delete':
            if (optional_param('confirm', 0, PARAM_BOOL)) {
                $tuning->delete();
                redirect($baseurl, get_string('tuningdeleted', 'tool_aibridge', $name));
            }
            echo $OUTPUT->header();
            $confirmurl = new moodle_url($baseurl, ['action' => 'delete', 'id' => $id, 'confirm' => 1]);
            echo $OUTPUT->confirm(get_string('confirmdeletetuning', 'tool_aibridge', s($name)), $confirmurl, $baseurl);
            echo $OUTPUT->footer();
            die;
    }
}

// The providers whose models the user may tune: those they can change, with an OpenAI-style API.
$providers = [];
foreach (provider::get_manageable($context, $USER->id) as $provider) {
    if ($provider->can_edit($USER->id) && tuning::is_tunable($provider)) {
        $providers[$provider->get_id()] = $provider->get_name() . ' (' . $provider->get_record()->model . ')';
    }
}
$sharingoptions = provider::get_sharing_options($context, $USER->id);

$form = null;
if ($providers) {
    $form = new tuning_form(null, ['contextid' => $contextid, 'providers' => $providers, 'sharingoptions' => $sharingoptions]);
    $form->set_data(['sharecontextid' => array_key_first($sharingoptions)]);
    if ($form->is_cancelled()) {
        redirect($baseurl);
    }
    if ($data = $form->get_data()) {
        if (!isset($providers[$data->providerid]) || !isset($sharingoptions[$data->sharecontextid])) {
            throw new moodle_exception('invalidrecord', 'error');
        }
        $tuning = tuning::create(
            provider::get($data->providerid),
            $data->name,
            $data->sharecontextid,
            $USER->id,
            $form->get_new_filename('trainingfile'),
            $form->get_file_content('trainingfile'),
            $data->basemodel
        );
        $tuning->advance();
        redirect($baseurl, get_string('tuningadded', 'tool_aibridge', s($data->name)));
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo html_writer::tag('p', get_string('tuningsintro', 'tool_aibridge'));

$tunings = tuning::get_manageable($context, $USER->id);
if ($tunings) {
    $table = new html_table();
    $table->head = [
        get_string('name'),
        get_string('tuningprovider', 'tool_aibridge'),
        get_string('basemodel', 'tool_aibridge'),
        get_string('status'),
        get_string('tunedprovider', 'tool_aibridge'),
        get_string('lastmodified'),
        get_string('actions'),
    ];
    $table->attributes['class'] = 'generaltable';
    foreach ($tunings as $tuning) {
        $record = $tuning->get_record();
        $status = get_string('tuningstatus_' . $record->status, 'tool_aibridge');
        if (!empty($record->error)) {
            $status .= html_writer::div(s($record->error), 'small text-muted');
        }
        $base = '';
        if ($record->providerid && $DB->record_exists(provider::TABLE, ['id' => $record->providerid])) {
            $base = s(provider::get($record->providerid)->get_name());
        }
        $tuned = '';
        if ($record->tunedproviderid && $DB->record_exists(provider::TABLE, ['id' => $record->tunedproviderid])) {
            $tuned = s(provider::get($record->tunedproviderid)->get_name()) .
                html_writer::div(s($record->tunedmodel), 'small text-muted');
        }
        $actions = '';
        if ($tuning->can_edit($USER->id)) {
            $params = ['id' => $record->id, 'sesskey' => sesskey()];
            if (!$tuning->is_finished()) {
                $actions .= $OUTPUT->action_icon(
                    new moodle_url($baseurl, $params + ['action' => 'check']),
                    new pix_icon('i/reload', get_string('tuningcheck', 'tool_aibridge'))
                );
            }
            $actions .= $OUTPUT->action_icon(
                new moodle_url($baseurl, $params + ['action' => 'delete']),
                new pix_icon('t/delete', get_string('delete'))
            );
        }
        $table->data[] = [s($record->name), $base, s($record->basemodel), $status, $tuned,
            userdate($record->timemodified, get_string('strftimedatetimeshort', 'langconfig')), $actions];
    }
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('notunings', 'tool_aibridge'), 'info');
}

echo $OUTPUT->heading(get_string('addtuning', 'tool_aibridge'), 3);
if ($form) {
    $form->display();
} else {
    echo $OUTPUT->notification(get_string('notunableproviders', 'tool_aibridge'), 'info');
}
echo $OUTPUT->footer();
