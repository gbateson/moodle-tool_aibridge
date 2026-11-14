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
 * Import templates into the AI prompt library.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/locallib.php');

use tool_aibridge\form\import_form;
use tool_aibridge\library;
use tool_aibridge\library\template;

$contextid = optional_param('contextid', SYSCONTEXTID, PARAM_INT);
$context = context::instance_by_id($contextid);
require_login();
$title = get_string('import', 'tool_aibridge');
tool_aibridge_setup_page(
    '/admin/tool/aibridge/import.php',
    ['contextid' => $contextid],
    $context,
    'tool/aibridge:addtemplate',
    'tool_aibridge_library',
    $title
);
$returnurl = new moodle_url('/admin/tool/aibridge/library.php', ['contextid' => $contextid]);

$sharingoptions = template::get_sharing_options($context, $USER->id);
$form = new import_form(null, ['contextid' => $contextid, 'sharingoptions' => $sharingoptions]);
$form->set_data(['sharecontextid' => array_key_first($sharingoptions)]);

if ($form->is_cancelled()) {
    redirect($returnurl);
}
$error = '';
if ($data = $form->get_data()) {
    $json = trim((string) $form->get_file_content('importfile'));
    if ($json === '') {
        $json = trim($data->importtext);
    }
    if (!isset($sharingoptions[$data->sharecontextid])) {
        throw new moodle_exception('invalidcontext');
    }
    try {
        $templates = library::import($json, $data->sharecontextid, $USER->id);
        redirect($returnurl, get_string('templatesimported', 'tool_aibridge', count($templates)));
    } catch (moodle_exception $e) {
        $error = $e->getMessage();
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
if ($error !== '') {
    echo $OUTPUT->notification(s($error), 'error');
}
$form->display();
echo $OUTPUT->footer();
