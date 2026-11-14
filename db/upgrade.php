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
 * Upgrade steps for the AI bridge.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the AI bridge.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_tool_aibridge_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    $newversion = 2026111402;
    if ($oldversion < $newversion) {
        // The library of prompts, formats and fragments.
        $table = new xmldb_table('tool_aibridge_templates');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('shortname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'prompt');
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('component', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
        $table->add_field('ownerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('parentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('qformat', XMLDB_TYPE_CHAR, '20', null, null, null, null);
        $table->add_field('body', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('instructions', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('examples', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('parameters', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('formatlinks', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('version', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sharedfrom', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('shareduntil', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('ownerid', XMLDB_KEY_FOREIGN, ['ownerid'], 'user', ['id']);
        $table->add_key('contextid', XMLDB_KEY_FOREIGN, ['contextid'], 'context', ['id']);
        $table->add_index('shortname', XMLDB_INDEX_UNIQUE, ['shortname']);
        $table->add_index('type', XMLDB_INDEX_NOTUNIQUE, ['type']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Earlier versions of each template.
        $table = new xmldb_table('tool_aibridge_tpl_versions');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('templateid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('version', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('qformat', XMLDB_TYPE_CHAR, '20', null, null, null, null);
        $table->add_field('body', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('instructions', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('examples', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('parameters', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('formatlinks', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('parentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('templateid', XMLDB_KEY_FOREIGN, ['templateid'], 'tool_aibridge_templates', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('templateversion', XMLDB_INDEX_UNIQUE, ['templateid', 'version']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Which template, and which version of it, made each request.
        $table = new xmldb_table('tool_aibridge_log');
        $field = new xmldb_field('templateid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'completiontokens');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('templateversion', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'templateid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, $newversion, 'tool', 'aibridge');
    }

    // Add or update the templates that plugins ship.
    \tool_aibridge\library::sync_all();

    return true;
}
