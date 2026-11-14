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
 * Add or edit an AI provider.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/locallib.php');

use tool_aibridge\form\provider_form;
use tool_aibridge\presets;
use tool_aibridge\provider;

$contextid = optional_param('contextid', SYSCONTEXTID, PARAM_INT);
$id = optional_param('id', 0, PARAM_INT);
$preset = optional_param('preset', '', PARAM_ALPHANUMEXT);

$context = context::instance_by_id($contextid);
require_login();
$title = get_string($id ? 'editprovider' : 'addprovider', 'tool_aibridge');
tool_aibridge_setup_page(
    '/admin/tool/aibridge/edit.php',
    ['contextid' => $contextid, 'id' => $id],
    $context,
    'tool/aibridge:addprovider',
    'tool_aibridge_providers',
    $title
);
$returnurl = new moodle_url('/admin/tool/aibridge/index.php', ['contextid' => $contextid]);

$sharingoptions = provider::get_sharing_options($context, $USER->id);
$provider = null;
if ($id) {
    $provider = provider::get($id);
    if (!$provider->can_edit($USER->id)) {
        throw new required_capability_exception($context, 'tool/aibridge:manageproviders', 'nopermissions', '');
    }
    // Keep the provider's own sharing, even if this user could not choose it.
    $record = $provider->get_record();
    if ($record->capability == provider::CAPABILITY_MULTIMODAL) {
        // Fields hidden by the form are not sent, so they may be missing.
        $record->uploadstyle = $data->uploadstyle ?? provider::UPLOAD_INLINE_BASE64;
        if ($record->uploadstyle == provider::UPLOAD_REFERENCE) {
            $record->uploadprotocol = $data->uploadprotocol ?? '';
            $record->uploadurl = trim($data->uploadurl ?? '');
        }
    }
    if (!isset($sharingoptions[$record->contextid])) {
        $sharedcontext = context::instance_by_id($record->contextid, IGNORE_MISSING);
        $sharingoptions[$record->contextid] = $sharedcontext ? $sharedcontext->get_context_name() : '?';
    }
}

$customdata = [
    'id' => $id,
    'contextid' => $contextid,
    'haskey' => $provider ? $provider->has_apikey() : false,
    'sharingoptions' => $sharingoptions,
];
$form = new provider_form(null, $customdata);

if ($form->is_cancelled()) {
    redirect($returnurl);
}
if ($form->no_submit_button_pressed()) {
    $preset = optional_param('preset', '', PARAM_ALPHANUMEXT);
    redirect(new moodle_url('/admin/tool/aibridge/edit.php', ['contextid' => $contextid, 'preset' => $preset]));
}

if ($data = $form->get_data()) {
    $record = (object) [
        'name' => trim($data->name),
        'capability' => $data->capability,
        'contextid' => $data->sharecontextid,
        'enabled' => $data->enabled,
        'url' => trim($data->url),
        'model' => trim($data->model ?? ''),
        'headers' => trim($data->headers ?? ''),
        'requesttemplate' => trim($data->requesttemplate ?? ''),
        'filetemplate' => trim($data->filetemplate ?? ''),
        'returnpath' => trim($data->returnpath ?? ''),
        'tokendefaults' => trim($data->tokendefaults ?? ''),
        'uploadstyle' => provider::UPLOAD_NONE,
        'uploadprotocol' => '',
        'uploadurl' => '',
        'datanotice' => trim($data->datanotice ?? ''),
        'userratelimit' => max(0, (int) $data->userratelimit),
        'sharedfrom' => empty($data->sharedfrom) ? 0 : $data->sharedfrom,
        'shareduntil' => empty($data->shareduntil) ? 0 : $data->shareduntil,
        'sortorder' => (int) $data->sortorder,
    ];
    if (!isset($sharingoptions[$record->contextid])) {
        throw new moodle_exception('invalidcontext');
    }
    $apikey = null;
    if (!empty($data->removekey)) {
        $apikey = '';
    } else if (trim((string) $data->apikey) !== '') {
        $apikey = trim($data->apikey);
    }
    if ($provider) {
        $record->id = $provider->get_id();
    } else {
        $record->ownerid = $USER->id;
    }
    $provider = provider::save($record, $apikey);
    redirect($returnurl, get_string('providersaved', 'tool_aibridge', $provider->get_name()));
}

if ($provider) {
    $defaults = clone($provider->get_record());
    $defaults->sharecontextid = $defaults->contextid;
    unset($defaults->apikey);
} else {
    $defaults = (object) [
        'sharecontextid' => array_key_first($sharingoptions),
        'enabled' => 1,
    ];
    $presets = presets::get_presets();
    if ($preset && isset($presets[$preset])) {
        foreach ($presets[$preset] as $name => $value) {
            $defaults->$name = $value;
        }
        $defaults->preset = $preset;
    }
}
$defaults->contextid = $contextid;
$defaults->id = $id;
$form->set_data($defaults);

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo $OUTPUT->notification(get_string('providerwarning', 'tool_aibridge'), 'info');
$form->display();
echo $OUTPUT->footer();
