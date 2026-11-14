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
 * Add, edit or view a template in the AI prompt library.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/locallib.php');

use tool_aibridge\form\template_form;
use tool_aibridge\library\template;
use tool_aibridge\sharing;

$contextid = optional_param('contextid', SYSCONTEXTID, PARAM_INT);
$id = optional_param('id', 0, PARAM_INT);
$type = optional_param('type', template::TYPE_PROMPT, PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHA);

$context = context::instance_by_id($contextid);
require_login();
$template = $id ? template::get($id) : null;
if ($template) {
    $type = $template->get_type();
}
if (!array_key_exists($type, template::get_type_options())) {
    throw new moodle_exception('invalidparameter', 'debug');
}
$title = $template ? $template->get_name() : get_string('addtemplate_' . $type, 'tool_aibridge');
tool_aibridge_setup_page(
    '/admin/tool/aibridge/template.php',
    ['contextid' => $contextid, 'id' => $id],
    $context,
    'tool/aibridge:addtemplate',
    'tool_aibridge_library',
    $title
);
$returnurl = new moodle_url('/admin/tool/aibridge/library.php', ['contextid' => $contextid]);
$canedit = !$template || $template->can_edit($USER->id);

if ($template && $action === 'restore' && $canedit) {
    require_sesskey();
    $version = required_param('version', PARAM_INT);
    $template = $template->restore_version($version, $USER->id);
    redirect($PAGE->url, get_string('versionrestored', 'tool_aibridge', $version));
}

if (!$canedit) {
    // A shipped template, or someone else's: show it, and offer a copy.
    echo $OUTPUT->header();
    echo $OUTPUT->heading(s($template->get_name()));
    $record = $template->get_record();
    if ($template->is_shipped()) {
        echo $OUTPUT->notification(get_string('templateshipped', 'tool_aibridge', $record->component), 'info');
    }
    echo tool_aibridge_template_details($template);
    $buttons = [];
    if (has_capability('tool/aibridge:addtemplate', $context)) {
        $copyurl = new moodle_url($returnurl, ['action' => 'copy', 'id' => $id, 'sesskey' => sesskey()]);
        $buttons[] = $OUTPUT->single_button($copyurl, get_string('copytoedit', 'tool_aibridge'), 'post');
    }
    if ($type === template::TYPE_PROMPT) {
        $previewurl = new moodle_url('/admin/tool/aibridge/preview.php', ['contextid' => $contextid, 'id' => $id]);
        $buttons[] = $OUTPUT->single_button($previewurl, get_string('preview', 'tool_aibridge'), 'get');
    }
    $buttons[] = $OUTPUT->single_button($returnurl, get_string('back'), 'get');
    echo html_writer::div(implode(' ', $buttons), 'd-flex flex-wrap gap-2 tool-aibridge-buttons');
    echo tool_aibridge_template_versions($template, false);
    echo $OUTPUT->footer();
    die;
}

// The prompts that this prompt can extend.
$parentoptions = [0 => get_string('none')];
if ($type === template::TYPE_PROMPT) {
    foreach (template::get_available($context, $USER->id, template::TYPE_PROMPT) as $prompt) {
        if (!$template || $prompt->get_id() != $template->get_id()) {
            $parentoptions[$prompt->get_id()] = $prompt->get_name() . ' (' . $prompt->get_shortname() . ')';
        }
    }
    if ($template && ($parent = $template->get_parent()) && !isset($parentoptions[$parent->get_id()])) {
        $parentoptions[$parent->get_id()] = $parent->get_name() . ' (' . $parent->get_shortname() . ')';
    }
}
$sharingoptions = template::get_sharing_options($context, $USER->id);
if ($template && !isset($sharingoptions[$template->get_record()->contextid])) {
    $sharingoptions[$template->get_record()->contextid] = sharing::get_description($template->get_record());
}

$form = new template_form(null, [
    'type' => $type,
    'contextid' => $contextid,
    'sharingoptions' => $sharingoptions,
    'parentoptions' => $parentoptions,
]);

if ($form->is_cancelled()) {
    redirect($returnurl);
}
if ($data = $form->get_data()) {
    if (!isset($sharingoptions[$data->sharecontextid])) {
        throw new moodle_exception('invalidcontext');
    }
    $record = (object) [
        'type' => $type,
        'name' => trim($data->name),
        'description' => trim($data->description),
        'body' => $data->body,
        'parameters' => trim($data->parameters),
        'contextid' => $data->sharecontextid,
        'enabled' => $data->enabled,
        'sharedfrom' => empty($data->sharedfrom) ? 0 : $data->sharedfrom,
        'shareduntil' => empty($data->shareduntil) ? 0 : $data->shareduntil,
    ];
    if (trim($data->shortname) !== '') {
        $record->shortname = trim($data->shortname);
    }
    if ($type === template::TYPE_PROMPT) {
        $record->parentid = (int) $data->parentid;
        $links = template_form::read_formatlinks($data->formatlinks ?? '');
        $record->formatlinks = $links ? $links : '';
    }
    if ($type === template::TYPE_FORMAT) {
        $record->qformat = $data->qformat;
        $record->instructions = $data->instructions ?? '';
        $record->examples = $data->examples ?? '';
    }
    if ($template) {
        $record->id = $template->get_id();
    } else {
        $record->ownerid = $USER->id;
        $record->component = '';
    }
    $template = template::save($record, $USER->id);
    redirect($returnurl, get_string('templatesaved', 'tool_aibridge', $template->get_name()));
}

if ($template) {
    $defaults = clone($template->get_record());
    $defaults->sharecontextid = $defaults->contextid;
    $defaults->formatlinks = template_form::write_formatlinks($template->get_formatlinks());
} else {
    $defaults = (object) ['sharecontextid' => array_key_first($sharingoptions), 'enabled' => 1, 'qformat' => 'gift'];
}
$defaults->contextid = $contextid;
$defaults->type = $type;
$form->set_data($defaults);

echo $OUTPUT->header();
echo $OUTPUT->heading(s($title));
$form->display();
if ($template) {
    echo tool_aibridge_template_versions($template, true);
}
echo $OUTPUT->footer();
