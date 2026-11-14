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
 * The form to try a prompt: choose the question type and format, and give values to its parameters.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preview_form extends \moodleform {
    /**
     * Define the form.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'contextid', $this->_customdata['contextid']);
        $mform->setType('contextid', PARAM_INT);

        $mform->addElement('select', 'qtype', get_string('questiontype', 'tool_aibridge'), $this->_customdata['qtypes']);
        $mform->addElement(
            'select',
            'format',
            get_string('templatetype_format', 'tool_aibridge'),
            $this->_customdata['formats']
        );
        $mform->addHelpButton('format', 'previewformat', 'tool_aibridge');

        foreach ($this->_customdata['parameters'] as $name => $parameter) {
            $label = s($name);
            if ($parameter['personal']) {
                $label .= ' ' . get_string('personaldata', 'tool_aibridge');
            }
            $field = 'param_' . str_replace('-', '_', $name);
            $mform->addElement('text', $field, $label, ['size' => 40, 'placeholder' => $parameter['default']]);
            $mform->setType($field, PARAM_RAW);
            if ($parameter['description'] !== '') {
                $description = \html_writer::tag('small', s($parameter['description']), ['class' => 'text-muted']);
                $mform->addElement('static', $field . '_desc', '', $description);
            }
        }

        $buttons = [
            $mform->createElement('submit', 'previewbutton', get_string('preview', 'tool_aibridge')),
            $mform->createElement('submit', 'sendbutton', get_string('sendtoai', 'tool_aibridge')),
        ];
        $mform->addGroup($buttons, 'buttons', '', ' ', false);
    }

    /**
     * Get the parameter values from the submitted data.
     *
     * @param \stdClass $data
     * @param array $parameters name => parameter.
     * @return array name => value, for the values that were given.
     */
    public static function get_values(\stdClass $data, array $parameters): array {
        $values = [];
        foreach (array_keys($parameters) as $name) {
            $field = 'param_' . str_replace('-', '_', $name);
            if (isset($data->$field) && trim($data->$field) !== '') {
                $values[$name] = trim($data->$field);
            }
        }
        if (!empty($data->format)) {
            $values['format'] = $data->format;
        }
        return $values;
    }
}
