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
 * Try a prompt: see the text that would be sent, and optionally send it and check the reply.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/locallib.php');

use tool_aibridge\form\preview_form;
use tool_aibridge\library;
use tool_aibridge\library\renderer;
use tool_aibridge\library\template;

$contextid = optional_param('contextid', SYSCONTEXTID, PARAM_INT);
$id = required_param('id', PARAM_INT);

$context = context::instance_by_id($contextid);
require_login();
$prompt = template::get($id);
$title = get_string('previewof', 'tool_aibridge', $prompt->get_name());
tool_aibridge_setup_page(
    '/admin/tool/aibridge/preview.php',
    ['contextid' => $contextid, 'id' => $id],
    $context,
    'tool/aibridge:addtemplate',
    'tool_aibridge_library',
    $title
);
if ($prompt->get_type() !== template::TYPE_PROMPT) {
    throw new moodle_exception('errornotaprompt', 'tool_aibridge', '', $prompt->get_shortname());
}
if (!$prompt->can_use($context, $USER->id) && !$prompt->can_edit($USER->id)) {
    throw new required_capability_exception($context, 'tool/aibridge:usetemplate', 'nopermissions', '');
}

// Make one piece of media from a marker, e.g. [[AUDIO ...]] or [[IMAGE ...]].
$mediaresponse = null;
$marker = optional_param('mediamarker', '', PARAM_RAW);
if ($marker !== '' && confirm_sesskey()) {
    $items = \tool_aibridge\library\parser::find_media($marker);
    if (empty($items)) {
        $items = [['type' => 'AUDIO', 'text' => $marker, 'attributes' => '', 'marker' => $marker]];
    }
    $mediaresponse = \tool_aibridge\media::generate($items[0], $context->id, $USER->id, ['component' => 'tool_aibridge']);
}

// The question types linked to formats, in this prompt and its parents.
$links = [];
$chain = [];
for ($template = $prompt; $template && count($chain) < renderer::MAX_DEPTH; $template = $template->get_parent()) {
    $chain[] = $template;
}
foreach (array_reverse($chain) as $template) {
    $links = array_merge($links, $template->get_formatlinks());
}
$qtypes = ['' => get_string('none')];
foreach (array_keys($links) as $qtype) {
    $qtypes[$qtype] = isset(renderer::QTYPE_NAMES[$qtype]) ? renderer::QTYPE_NAMES[$qtype] . " ($qtype)" : $qtype;
}
$formats = ['' => get_string('linkedformat', 'tool_aibridge')];
foreach (template::get_available($context, $USER->id, template::TYPE_FORMAT) as $format) {
    $formats[$format->get_shortname()] = $format->get_name();
}

// Render once with the defaults, to find the parameters of every template used.
$qtype = optional_param('qtype', (string) array_key_first($links), PARAM_ALPHANUMEXT);
$rendering = library::render($prompt, $context, $USER->id, [], $qtype);
$parameters = $rendering->get_parameters();
foreach (['output-format', 'question-format', 'type-of-questions'] as $automatic) {
    unset($parameters[$automatic]);
}

$form = new preview_form(null, [
    'id' => $id,
    'contextid' => $contextid,
    'qtypes' => $qtypes,
    'formats' => $formats,
    'parameters' => $parameters,
]);
$form->set_data(['qtype' => $qtype]);

$response = null;
$parsed = null;
if ($data = $form->get_data()) {
    $values = preview_form::get_values($data, $parameters);
    $rendering = library::render($prompt, $context, $USER->id, $values, (string) $data->qtype);
    if (!empty($data->sendbutton)) {
        [$response, $parsed] = library::generate($rendering, $context, $USER->id, 'tool_aibridge');
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(s($title));
$form->display();

echo $OUTPUT->heading(get_string('renderedprompt', 'tool_aibridge'), 3);
foreach ($rendering->get_errors() as $error) {
    echo $OUTPUT->notification(s($error), 'error');
}
foreach ($rendering->get_warnings() as $warning) {
    echo $OUTPUT->notification(s($warning), 'warning');
}
if ($personal = $rendering->get_personal()) {
    echo $OUTPUT->notification(get_string('personaldatawarning', 'tool_aibridge', implode(', ', $personal)), 'info');
}
echo html_writer::tag('pre', s($rendering->get_text()), ['class' => 'tool-aibridge-text border p-2']);
$used = [];
foreach ($rendering->get_versions() as $shortname => $version) {
    $used[] = s($shortname) . ' (v' . $version . ')';
}
echo html_writer::tag(
    'p',
    html_writer::tag('small', get_string('templatesused', 'tool_aibridge') . ': ' .
    implode(', ', $used) . '. ' . get_string('replyformat', 'tool_aibridge') . ': ' . s($rendering->get_qformat()) . '.'),
    ['class' => 'text-muted']
);

if ($response) {
    echo $OUTPUT->heading(get_string('reply', 'tool_aibridge'), 3);
    if (!$response->get_success()) {
        $message = s($response->get_error());
        if ($response->get_errorcode() === 'policynotaccepted') {
            $message .= ' ' . html_writer::link(
                \tool_aibridge\policy::get_url($context, $PAGE->url),
                get_string('policy', 'tool_aibridge')
            );
        }
        echo $OUTPUT->notification($message, 'error');
    } else {
        echo html_writer::tag('p', s($response->get_label()) . ' ' .
            html_writer::tag('small', s($response->get_model()), ['class' => 'text-muted']));
        foreach ($parsed->get_errors() as $error) {
            echo $OUTPUT->notification(s($error), 'error');
        }
        foreach ($parsed->get_warnings() as $warning) {
            echo $OUTPUT->notification(s($warning), 'warning');
        }
        if ($parsed->is_valid()) {
            echo $OUTPUT->notification(get_string('replyvalid', 'tool_aibridge', $parsed->get_count()), 'success');
        }
        echo html_writer::tag('pre', s($parsed->get_text()), ['class' => 'tool-aibridge-text border p-2']);
        if ($media = $parsed->get_media()) {
            $table = new html_table();
            $table->head = [get_string('mediatype', 'tool_aibridge'), get_string('mediatext', 'tool_aibridge'), ''];
            $table->attributes['class'] = 'generaltable';
            foreach ($media as $item) {
                $table->data[] = [$item['type'], html_writer::tag('pre', s($item['text']), ['class' => 'tool-aibridge-text']),
                    tool_aibridge_media_button($PAGE->url, $item['marker'])];
            }
            echo $OUTPUT->heading(get_string('media', 'tool_aibridge'), 4);
            echo html_writer::table($table);
        }
    }
}

// Try a media marker, or a conversation.
echo $OUTPUT->heading(get_string('trymedia', 'tool_aibridge'), 3);
if ($mediaresponse) {
    echo tool_aibridge_media_result($mediaresponse);
}
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::tag('textarea', s($marker), ['name' => 'mediamarker', 'rows' => 8, 'cols' => 80,
    'class' => 'form-control', 'style' => 'font-family: monospace', 'aria-label' => get_string('trymedia', 'tool_aibridge'),
    'placeholder' => "[[AUDIO\nA=woman (customer)\nB=man (shop assistant)\nA: Hello.\nB: Good morning.\n]]"]);
echo html_writer::tag('p', html_writer::tag('small', get_string('trymedia_help', 'tool_aibridge'), ['class' => 'text-muted']));
echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-secondary',
    'value' => get_string('makemedia', 'tool_aibridge')]);
echo html_writer::end_tag('form');
echo $OUTPUT->footer();
