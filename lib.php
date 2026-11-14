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
 * Callbacks for the AI bridge.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add links to the AI providers and the AI log to a course's "More" menu.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context $context
 */
function tool_aibridge_extend_navigation_course($navigation, $course, $context) {
    if (!get_config('tool_aibridge', 'allowuserproviders')) {
        $canadd = false;
    } else {
        $canadd = has_capability('tool/aibridge:addprovider', $context);
    }
    $canviewlog = has_capability('tool/aibridge:viewlog', $context);
    $canaddtemplate = has_capability('tool/aibridge:addtemplate', $context);
    if (!$canadd && !$canviewlog && !$canaddtemplate) {
        return;
    }
    $params = ['contextid' => $context->id];
    if ($canadd) {
        $navigation->add(
            get_string('providers', 'tool_aibridge'),
            new moodle_url('/admin/tool/aibridge/index.php', $params),
            navigation_node::TYPE_SETTING,
            null,
            'tool_aibridge_providers',
            new pix_icon('i/settings', '')
        );
    }
    if ($canaddtemplate) {
        $navigation->add(
            get_string('library', 'tool_aibridge'),
            new moodle_url('/admin/tool/aibridge/library.php', $params),
            navigation_node::TYPE_SETTING,
            null,
            'tool_aibridge_library',
            new pix_icon('i/edit', '')
        );
    }
    if ($canviewlog) {
        $navigation->add(
            get_string('log', 'tool_aibridge'),
            new moodle_url('/admin/tool/aibridge/log.php', $params),
            navigation_node::TYPE_SETTING,
            null,
            'tool_aibridge_log',
            new pix_icon('i/report', '')
        );
    }
}

/**
 * Before a course is deleted, keep the templates shared in it, and delete its providers.
 *
 * @param stdClass $course
 */
function tool_aibridge_pre_course_delete($course) {
    \tool_aibridge\sharing::context_deleted(context_course::instance($course->id));
}

/**
 * Before a course category is deleted, keep the templates shared in it, and delete its providers.
 *
 * @param stdClass $category
 */
function tool_aibridge_pre_course_category_delete($category) {
    \tool_aibridge\sharing::context_deleted(context_coursecat::instance($category->id));
}
