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
 * Install steps for the AI bridge.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add the templates that plugins ship, and tell plugins that the bridge is
 * installed, e.g. so that they can move their own AI settings to the bridge.
 *
 * Moodle installs and upgrades admin tools after many other kinds of plugin,
 * so a plugin that is upgraded in the same run as the bridge is installed
 * cannot use the bridge's tables in its own upgrade steps. Instead, it can
 * have a function called {component}_aibridge_installed() in its lib.php.
 */
function xmldb_tool_aibridge_install() {
    \tool_aibridge\library::sync_all();
    foreach (get_plugins_with_function('aibridge_installed') as $plugins) {
        foreach ($plugins as $function) {
            $function();
        }
    }
}
