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
 * Manage AI providers: the site's own, or those shared in a course or category.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/locallib.php');

use tool_aibridge\provider;

$contextid = optional_param('contextid', SYSCONTEXTID, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

$context = context::instance_by_id($contextid);
require_login();
$capability = 'tool/aibridge:addprovider';
$title = get_string('providers', 'tool_aibridge');
tool_aibridge_setup_page(
    '/admin/tool/aibridge/index.php',
    ['contextid' => $contextid],
    $context,
    $capability,
    'tool_aibridge_providers',
    $title
);
$baseurl = new moodle_url('/admin/tool/aibridge/index.php', ['contextid' => $contextid]);

$provider = null;
if ($id) {
    $provider = provider::get($id);
    if (!$provider->can_edit($USER->id)) {
        throw new required_capability_exception($context, 'tool/aibridge:manageproviders', 'nopermissions', '');
    }
}

$testresponse = null;
if ($provider && $action) {
    require_sesskey();
    switch ($action) {
        case 'enable':
        case 'disable':
            $DB->set_field(provider::TABLE, 'enabled', ($action == 'enable') ? 1 : 0, ['id' => $id]);
            redirect($baseurl);
            break;
        case 'delete':
            if (optional_param('confirm', 0, PARAM_BOOL)) {
                $provider->delete();
                redirect($baseurl, get_string('providerdeleted', 'tool_aibridge', $provider->get_name()));
            }
            echo $OUTPUT->header();
            $confirmurl = new moodle_url($baseurl, ['action' => 'delete', 'id' => $id, 'confirm' => 1]);
            echo $OUTPUT->confirm(get_string('confirmdelete', 'tool_aibridge', s($provider->get_name())), $confirmurl, $baseurl);
            echo $OUTPUT->footer();
            die;
        case 'test':
            $testresponse = \tool_aibridge\manager::test($provider, $context, $USER->id);
            break;
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);

if (!\tool_aibridge\manager::is_enabled()) {
    echo $OUTPUT->notification(get_string('bridgedisabled', 'tool_aibridge'), 'warning');
} else if (!\tool_aibridge\manager::is_external_allowed()) {
    echo $OUTPUT->notification(get_string('externaldisabled', 'tool_aibridge'), 'warning');
}

if ($testresponse) {
    $name = s($provider->get_name());
    if (!$testresponse->get_success()) {
        echo $OUTPUT->notification(get_string('testfailed', 'tool_aibridge', $name) . '<br>' .
                s($testresponse->get_error()), 'error');
    } else if ($testresponse->get_audio() !== '') {
        $src = 'data:' . $testresponse->get_mimetype() . ';base64,' . base64_encode($testresponse->get_audio());
        echo $OUTPUT->notification(get_string('testsucceeded', 'tool_aibridge', $name) . '<br>' .
                html_writer::tag('audio', '', ['controls' => 'controls', 'src' => $src]), 'success');
    } else {
        echo $OUTPUT->notification(get_string('testsucceeded', 'tool_aibridge', $name) . '<br>' .
                s(shorten_text($testresponse->get_content(), 500)), 'success');
    }
}

echo html_writer::tag('p', get_string('providersintro', 'tool_aibridge'));

$providers = provider::get_manageable($context, $USER->id);
if (empty($providers)) {
    echo $OUTPUT->notification(get_string('noproviders', 'tool_aibridge'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('name'),
        get_string('capability', 'tool_aibridge'),
        get_string('sharewith', 'tool_aibridge'),
        get_string('owner', 'tool_aibridge'),
        get_string('model', 'tool_aibridge'),
        get_string('host', 'tool_aibridge'),
        get_string('apikey', 'tool_aibridge'),
        get_string('actions'),
    ];
    $table->attributes['class'] = 'generaltable';
    $table->colclasses = [7 => 'text-nowrap'];
    $capabilities = provider::get_capability_options();
    $owners = [];
    foreach ($providers as $provider) {
        $record = $provider->get_record();
        $providercontext = context::instance_by_id($record->contextid, IGNORE_MISSING);
        if (!$providercontext) {
            $sharedwith = '?';
        } else if ($providercontext->contextlevel == CONTEXT_USER) {
            $sharedwith = get_string('shareonlyowner', 'tool_aibridge');
        } else if ($providercontext->contextlevel == CONTEXT_SYSTEM) {
            $sharedwith = get_string('sharesite', 'tool_aibridge');
        } else {
            $sharedwith = $providercontext->get_context_name();
        }
        if (!isset($owners[$record->ownerid])) {
            $owner = $DB->get_record('user', ['id' => $record->ownerid]);
            $owners[$record->ownerid] = $owner ? fullname($owner) : '-';
        }
        $actions = [];
        if ($provider->can_edit($USER->id)) {
            $params = ['contextid' => $contextid, 'id' => $record->id, 'sesskey' => sesskey()];
            $actions[] = $OUTPUT->action_icon(new moodle_url(
                '/admin/tool/aibridge/edit.php',
                ['contextid' => $contextid, 'id' => $record->id]
            ), new pix_icon('t/edit', get_string('edit')));
            if ($record->enabled) {
                $actions[] = $OUTPUT->action_icon(
                    new moodle_url($baseurl, ['action' => 'disable'] + $params),
                    new pix_icon('t/hide', get_string('disable'))
                );
            } else {
                $actions[] = $OUTPUT->action_icon(
                    new moodle_url($baseurl, ['action' => 'enable'] + $params),
                    new pix_icon('t/show', get_string('enable'))
                );
            }
            $actions[] = $OUTPUT->action_icon(
                new moodle_url($baseurl, ['action' => 'test'] + $params),
                new pix_icon('t/check', get_string('test', 'tool_aibridge'))
            );
            $actions[] = $OUTPUT->action_icon(
                new moodle_url($baseurl, ['action' => 'delete'] + $params),
                new pix_icon('t/delete', get_string('delete'))
            );
        }
        $name = s($record->name);
        if (!$provider->is_active()) {
            $name = html_writer::tag('span', $name, ['class' => 'dimmed_text']);
        }
        $table->data[] = [
            $name,
            isset($capabilities[$record->capability]) ? $capabilities[$record->capability] : s($record->capability),
            $sharedwith,
            $owners[$record->ownerid],
            s($record->model),
            s($provider->get_host()),
            $provider->has_apikey() ? get_string('apikeyset', 'tool_aibridge') : '-',
            implode(' ', $actions),
        ];
    }
    echo html_writer::table($table);
}

$addurl = new moodle_url('/admin/tool/aibridge/edit.php', ['contextid' => $contextid]);
echo $OUTPUT->single_button($addurl, get_string('addprovider', 'tool_aibridge'), 'get');

echo $OUTPUT->footer();
