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
 * Functions for the AI bridge pages.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Set up a page of the AI bridge in a context, and check the user may see it.
 *
 * @param string $path e.g. "/admin/tool/aibridge/index.php".
 * @param array $params the page's URL parameters.
 * @param context $context the site, a course category or a course.
 * @param string $capability the capability needed in a course or category.
 * @param string $adminpage the admin page name, used for the site.
 * @param string $title
 */
function tool_aibridge_setup_page(
    string $path,
    array $params,
    context $context,
    string $capability,
    string $adminpage,
    string $title
) {
    global $CFG, $PAGE;
    $url = new moodle_url($path, $params);
    if ($context->contextlevel == CONTEXT_SYSTEM) {
        require_once($CFG->libdir . '/adminlib.php');
        admin_externalpage_setup($adminpage, '', null, $url);
    } else if ($context->contextlevel == CONTEXT_COURSE) {
        require_login($context->instanceid);
        require_capability($capability, $context);
        $PAGE->set_url($url);
        $PAGE->set_pagelayout('admin');
    } else if ($context->contextlevel == CONTEXT_COURSECAT) {
        require_login();
        require_capability($capability, $context);
        $PAGE->set_context($context);
        $PAGE->set_url($url);
        $PAGE->set_pagelayout('admin');
    } else {
        throw new moodle_exception('invalidcontext');
    }
    $PAGE->set_title($title);
    $PAGE->set_heading($context->contextlevel == CONTEXT_SYSTEM ? $title : $context->get_context_name(false));
}

/**
 * Show the fields of a template.
 *
 * @param \tool_aibridge\library\template $template
 * @return string HTML.
 */
function tool_aibridge_template_details(\tool_aibridge\library\template $template): string {
    $record = $template->get_record();
    $rows = [
        get_string('shortname', 'tool_aibridge') => s($record->shortname),
        get_string('type', 'tool_aibridge') => get_string('templatetype_' . $record->type, 'tool_aibridge'),
        get_string('version') => $record->version,
    ];
    if ($record->description !== '' && $record->description !== null) {
        $rows[get_string('description')] = s($record->description);
    }
    if ($parent = $template->get_parent()) {
        $rows[get_string('extends', 'tool_aibridge')] = s($parent->get_name() . ' (' . $parent->get_shortname() . ')');
    }
    if ($record->type === \tool_aibridge\library\template::TYPE_FORMAT) {
        $qformats = \tool_aibridge\library\template::get_qformat_options();
        $rows[get_string('qformat', 'tool_aibridge')] = isset($qformats[$record->qformat]) ? $qformats[$record->qformat] : '';
    }
    $fields = [
        'body' => get_string('body_' . $record->type, 'tool_aibridge'),
        'instructions' => get_string('instructions', 'tool_aibridge'),
        'examples' => get_string('examples', 'tool_aibridge'),
    ];
    foreach ($fields as $field => $label) {
        if (trim((string) $record->$field) !== '') {
            $rows[$label] = html_writer::tag('pre', s($record->$field), ['class' => 'tool-aibridge-text']);
        }
    }
    if ($links = $template->get_formatlinks()) {
        $rows[get_string('formatlinks', 'tool_aibridge')] = html_writer::tag(
            'pre',
            s(\tool_aibridge\form\template_form::write_formatlinks($links)),
            ['class' => 'tool-aibridge-text']
        );
    }
    if (trim((string) $record->parameters) !== '') {
        $rows[get_string('parameters', 'tool_aibridge')] = html_writer::tag(
            'pre',
            s($record->parameters),
            ['class' => 'tool-aibridge-text']
        );
    }
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    foreach ($rows as $label => $value) {
        $table->data[] = [html_writer::tag('strong', $label), $value];
    }
    return html_writer::table($table);
}

/**
 * Show the versions of a template.
 *
 * @param \tool_aibridge\library\template $template
 * @param bool $canrestore
 * @return string HTML.
 */
function tool_aibridge_template_versions(\tool_aibridge\library\template $template, bool $canrestore): string {
    global $DB, $OUTPUT, $PAGE;
    $versions = $template->get_versions();
    if (count($versions) < 2) {
        return '';
    }
    $table = new html_table();
    $table->head = [get_string('version'), get_string('time'), get_string('user'), get_string('name'), ''];
    $table->attributes['class'] = 'generaltable';
    foreach ($versions as $version) {
        $user = $version->userid ? $DB->get_record('user', ['id' => $version->userid]) : null;
        $action = '';
        if ($canrestore && $version->version != $template->get_version()) {
            $url = new moodle_url($PAGE->url, ['action' => 'restore', 'version' => $version->version, 'sesskey' => sesskey()]);
            $action = $OUTPUT->single_button($url, get_string('restoreversion', 'tool_aibridge'), 'post');
        }
        $body = html_writer::tag('details', html_writer::tag('summary', s($version->name)) .
            html_writer::tag('pre', s($version->body), ['class' => 'tool-aibridge-text']));
        $table->data[] = [
            $version->version,
            userdate($version->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
            $user ? fullname($user) : '-',
            $body,
            $action,
        ];
    }
    return $OUTPUT->heading(get_string('versions', 'tool_aibridge'), 3) . html_writer::table($table);
}

/**
 * A button that makes one piece of media from its marker.
 *
 * @param moodle_url $url the page.
 * @param string $marker e.g. [[AUDIO Hello.]]
 * @return string HTML.
 */
function tool_aibridge_media_button(moodle_url $url, string $marker): string {
    return html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'mediamarker', 'value' => $marker]) .
        html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-secondary btn-sm',
            'value' => get_string('makemedia', 'tool_aibridge')]) .
        html_writer::end_tag('form');
}

/**
 * Show media that was made: an audio player or an image, or the error.
 *
 * @param \tool_aibridge\response $response
 * @return string HTML.
 */
function tool_aibridge_media_result(\tool_aibridge\response $response): string {
    global $OUTPUT;
    if (!$response->get_success()) {
        return $OUTPUT->notification(s($response->get_error()), 'error');
    }
    $label = get_string('mediamade', 'tool_aibridge', s($response->get_providername()));
    if ($response->get_content() !== '') {
        $label .= ' (' . s($response->get_content()) . ')';
    }
    if ($response->get_image() !== '') {
        $src = 'data:' . $response->get_mimetype() . ';base64,' . base64_encode($response->get_image());
        $media = html_writer::empty_tag('img', ['src' => $src, 'alt' => '', 'style' => 'max-width: 100%; max-height: 512px;']);
    } else {
        $src = 'data:' . $response->get_mimetype() . ';base64,' . base64_encode($response->get_audio());
        $media = html_writer::tag('audio', '', ['controls' => 'controls', 'src' => $src]);
    }
    return $OUTPUT->notification($label . ' ' . display_size(strlen($response->get_image() . $response->get_audio())) .
        '<br>' . $media, 'success');
}
