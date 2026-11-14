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
