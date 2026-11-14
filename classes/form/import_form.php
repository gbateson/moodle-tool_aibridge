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

namespace tool_aibridge\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * The form to import templates into the AI prompt library.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_form extends \moodleform {
    /**
     * Define the form.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'contextid', $this->_customdata['contextid']);
        $mform->setType('contextid', PARAM_INT);

        $mform->addElement(
            'filepicker',
            'importfile',
            get_string('importfile', 'tool_aibridge'),
            null,
            ['accepted_types' => ['.json'], 'maxbytes' => 1048576]
        );
        $mform->addElement(
            'textarea',
            'importtext',
            get_string('importtext', 'tool_aibridge'),
            ['rows' => 10, 'cols' => 80, 'style' => 'font-family: monospace']
        );
        $mform->setType('importtext', PARAM_RAW);
        $mform->addHelpButton('importtext', 'importtext', 'tool_aibridge');

        $mform->addElement(
            'select',
            'sharecontextid',
            get_string('sharewith', 'tool_aibridge'),
            $this->_customdata['sharingoptions']
        );

        $this->add_action_buttons(true, get_string('import', 'tool_aibridge'));
    }
}
