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
 * The AI prompt library: prompts, output formats and fragments.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/locallib.php');

use tool_aibridge\library;
use tool_aibridge\library\template;
use tool_aibridge\sharing;

$contextid = optional_param('contextid', SYSCONTEXTID, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

$context = context::instance_by_id($contextid);
require_login();
$title = get_string('library', 'tool_aibridge');
tool_aibridge_setup_page(
    '/admin/tool/aibridge/library.php',
    ['contextid' => $contextid],
    $context,
    'tool/aibridge:addtemplate',
    'tool_aibridge_library',
    $title
);
$baseurl = new moodle_url('/admin/tool/aibridge/library.php', ['contextid' => $contextid]);

if ($action === 'exportall') {
    require_sesskey();
    $templates = array_filter(template::get_manageable($context, $USER->id), function ($template) {
        return !$template->is_shipped();
    });
    send_file(library::export($templates), 'ai-prompt-library.json', 0, 0, true, true, 'application/json');
}

if ($id && $action) {
    require_sesskey();
    $template = template::get($id);
    if ($action === 'export') {
        if (!$template->can_use($context, $USER->id) && !$template->can_edit($USER->id)) {
            throw new required_capability_exception($context, 'tool/aibridge:usetemplate', 'nopermissions', '');
        }
        send_file(library::export([$template]), $template->get_shortname() . '.json', 0, 0, true, true, 'application/json');
    }
    if ($action === 'copy') {
        $options = template::get_sharing_options($context, $USER->id);
        $copy = $template->copy(isset($options[$context->id]) ? $context->id : array_key_first($options), $USER->id);
        redirect(
            new moodle_url('/admin/tool/aibridge/template.php', ['contextid' => $contextid, 'id' => $copy->get_id()]),
            get_string('templatecopied', 'tool_aibridge', $template->get_name())
        );
    }
    if (!$template->can_edit($USER->id)) {
        throw new required_capability_exception($context, 'tool/aibridge:managetemplates', 'nopermissions', '');
    }
    switch ($action) {
        case 'enable':
        case 'disable':
            $DB->set_field(template::TABLE, 'enabled', ($action == 'enable') ? 1 : 0, ['id' => $id]);
            redirect($baseurl);
            break;
        case 'delete':
            if ($template->get_children()) {
                redirect(
                    $baseurl,
                    get_string('errortemplatehaschildren', 'tool_aibridge', $template->get_name()),
                    null,
                    \core\output\notification::NOTIFY_ERROR
                );
            }
            if (optional_param('confirm', 0, PARAM_BOOL)) {
                $template->delete();
                redirect($baseurl, get_string('templatedeleted', 'tool_aibridge', $template->get_name()));
            }
            echo $OUTPUT->header();
            $confirmurl = new moodle_url($baseurl, ['action' => 'delete', 'id' => $id, 'confirm' => 1]);
            echo $OUTPUT->confirm(
                get_string('confirmdeletetemplate', 'tool_aibridge', s($template->get_name())),
                $confirmurl,
                $baseurl
            );
            echo $OUTPUT->footer();
            die;
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo html_writer::tag('p', get_string('libraryintro', 'tool_aibridge'));

$templates = template::get_manageable($context, $USER->id);
$types = template::get_type_options();
$qformats = template::get_qformat_options();
$canadd = has_capability('tool/aibridge:addtemplate', $context) ||
    has_capability('tool/aibridge:managetemplates', context_system::instance());
$owners = [];
foreach ($types as $type => $typename) {
    echo $OUTPUT->heading(get_string('templatetypes_' . $type, 'tool_aibridge'), 3);
    $rows = [];
    foreach ($templates as $template) {
        if ($template->get_type() !== $type) {
            continue;
        }
        $record = $template->get_record();
        if ($template->is_shipped()) {
            $owner = get_string('shippedby', 'tool_aibridge', $record->component);
        } else {
            if (!isset($owners[$record->ownerid])) {
                $user = $DB->get_record('user', ['id' => $record->ownerid]);
                $owners[$record->ownerid] = $user ? fullname($user) : '-';
            }
            $owner = $owners[$record->ownerid];
        }
        $params = ['contextid' => $contextid, 'id' => $record->id];
        $sesskey = ['sesskey' => sesskey()];
        $actions = [];
        $viewurl = new moodle_url('/admin/tool/aibridge/template.php', $params);
        if ($type === template::TYPE_PROMPT) {
            $actions[] = $OUTPUT->action_icon(
                new moodle_url('/admin/tool/aibridge/preview.php', $params),
                new pix_icon('t/preview', get_string('preview', 'tool_aibridge'))
            );
        }
        if ($template->can_edit($USER->id)) {
            $actions[] = $OUTPUT->action_icon($viewurl, new pix_icon('t/edit', get_string('edit')));
        } else {
            $actions[] = $OUTPUT->action_icon($viewurl, new pix_icon('i/hide', get_string('view')));
        }
        if ($canadd) {
            $actions[] = $OUTPUT->action_icon(
                new moodle_url($baseurl, ['action' => 'copy'] + $params + $sesskey),
                new pix_icon('t/copy', get_string('copy'))
            );
        }
        $actions[] = $OUTPUT->action_icon(
            new moodle_url($baseurl, ['action' => 'export'] + $params + $sesskey),
            new pix_icon('t/download', get_string('export', 'tool_aibridge'))
        );
        if ($template->can_edit($USER->id)) {
            $toggle = $record->enabled ? 'disable' : 'enable';
            $actions[] = $OUTPUT->action_icon(
                new moodle_url($baseurl, ['action' => $toggle] + $params + $sesskey),
                new pix_icon($record->enabled ? 't/hide' : 't/show', get_string($toggle))
            );
            $actions[] = $OUTPUT->action_icon(
                new moodle_url($baseurl, ['action' => 'delete'] + $params + $sesskey),
                new pix_icon('t/delete', get_string('delete'))
            );
        }
        $name = html_writer::link($viewurl, s($record->name));
        if (!sharing::is_active($record)) {
            $name = html_writer::tag('span', $name, ['class' => 'dimmed_text']);
        }
        $detail = '';
        if ($type === template::TYPE_FORMAT && isset($qformats[$record->qformat])) {
            $detail = $qformats[$record->qformat];
        } else if ($type === template::TYPE_PROMPT && ($parent = $template->get_parent())) {
            $detail = get_string('extends', 'tool_aibridge') . ': ' . s($parent->get_name());
        }
        $rows[] = [
            $name . html_writer::tag('div', html_writer::tag('small', s($record->shortname)), ['class' => 'text-muted']),
            $detail,
            sharing::get_description($record),
            $owner,
            $record->version,
            implode(' ', $actions),
        ];
    }
    if (empty($rows)) {
        echo html_writer::tag('p', get_string('notemplates', 'tool_aibridge'), ['class' => 'text-muted']);
        continue;
    }
    $table = new html_table();
    $table->head = [
        get_string('name'),
        '',
        get_string('sharewith', 'tool_aibridge'),
        get_string('owner', 'tool_aibridge'),
        get_string('version'),
        get_string('actions'),
    ];
    $table->attributes['class'] = 'generaltable';
    $table->colclasses = [5 => 'text-nowrap'];
    $table->data = $rows;
    echo html_writer::table($table);
}

if ($canadd) {
    $buttons = [];
    foreach ($types as $type => $typename) {
        $url = new moodle_url('/admin/tool/aibridge/template.php', ['contextid' => $contextid, 'type' => $type]);
        $buttons[] = $OUTPUT->single_button($url, get_string('addtemplate_' . $type, 'tool_aibridge'), 'get');
    }
    $buttons[] = $OUTPUT->single_button(
        new moodle_url('/admin/tool/aibridge/import.php', ['contextid' => $contextid]),
        get_string('import', 'tool_aibridge'),
        'get'
    );
    $buttons[] = $OUTPUT->single_button(
        new moodle_url($baseurl, ['action' => 'exportall', 'sesskey' => sesskey()]),
        get_string('exportall', 'tool_aibridge'),
        'post'
    );
    echo html_writer::div(implode(' ', $buttons), 'd-flex flex-wrap gap-2 tool-aibridge-buttons');
}
echo $OUTPUT->footer();
