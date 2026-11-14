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

use tool_aibridge\library\template;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * The form to add or edit a template in the AI prompt library.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_form extends \moodleform {
    /**
     * Define the form.
     */
    protected function definition() {
        $mform = $this->_form;
        $type = $this->_customdata['type'];
        $monospace = 'font-family: monospace';

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'contextid', $this->_customdata['contextid']);
        $mform->setType('contextid', PARAM_INT);
        $mform->addElement('hidden', 'type', $type);
        $mform->setType('type', PARAM_ALPHA);

        $mform->addElement('header', 'generalheader', get_string('templatetype_' . $type, 'tool_aibridge'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('text', 'shortname', get_string('shortname', 'tool_aibridge'), ['size' => 40]);
        $mform->setType('shortname', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('shortname', 'shortname', 'tool_aibridge');

        $mform->addElement('textarea', 'description', get_string('description'), ['rows' => 2, 'cols' => 60]);
        $mform->setType('description', PARAM_TEXT);

        if ($type === template::TYPE_PROMPT) {
            $mform->addElement('select', 'parentid', get_string('extends', 'tool_aibridge'), $this->_customdata['parentoptions']);
            $mform->addHelpButton('parentid', 'extends', 'tool_aibridge');
        }
        if ($type === template::TYPE_FORMAT) {
            $mform->addElement('select', 'qformat', get_string('qformat', 'tool_aibridge'), template::get_qformat_options());
            $mform->addHelpButton('qformat', 'qformat', 'tool_aibridge');
        }

        $mform->addElement(
            'textarea',
            'body',
            get_string('body_' . $type, 'tool_aibridge'),
            ['rows' => 16, 'cols' => 80, 'style' => $monospace]
        );
        $mform->setType('body', PARAM_RAW);
        $mform->addRule('body', null, 'required', null, 'client');
        $mform->addHelpButton('body', 'body_' . $type, 'tool_aibridge');

        if ($type === template::TYPE_FORMAT) {
            $mform->addElement(
                'textarea',
                'instructions',
                get_string('instructions', 'tool_aibridge'),
                ['rows' => 10, 'cols' => 80, 'style' => $monospace]
            );
            $mform->setType('instructions', PARAM_RAW);
            $mform->addHelpButton('instructions', 'instructions', 'tool_aibridge');
            $mform->addElement(
                'textarea',
                'examples',
                get_string('examples', 'tool_aibridge'),
                ['rows' => 10, 'cols' => 80, 'style' => $monospace]
            );
            $mform->setType('examples', PARAM_RAW);
            $mform->addHelpButton('examples', 'examples', 'tool_aibridge');
        }
        if ($type === template::TYPE_PROMPT) {
            $mform->addElement(
                'textarea',
                'formatlinks',
                get_string('formatlinks', 'tool_aibridge'),
                ['rows' => 4, 'cols' => 80, 'style' => $monospace]
            );
            $mform->setType('formatlinks', PARAM_RAW);
            $mform->addHelpButton('formatlinks', 'formatlinks', 'tool_aibridge');
        }

        $mform->addElement(
            'textarea',
            'parameters',
            get_string('parameters', 'tool_aibridge'),
            ['rows' => 6, 'cols' => 80, 'style' => $monospace]
        );
        $mform->setType('parameters', PARAM_RAW);
        $mform->addHelpButton('parameters', 'parameters', 'tool_aibridge');

        $mform->addElement('header', 'sharingheader', get_string('sharing', 'tool_aibridge'));
        $mform->addElement(
            'select',
            'sharecontextid',
            get_string('sharewith', 'tool_aibridge'),
            $this->_customdata['sharingoptions']
        );
        $mform->addHelpButton('sharecontextid', 'sharetemplate', 'tool_aibridge');
        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'tool_aibridge'));
        $mform->setDefault('enabled', 1);
        $mform->addElement('date_time_selector', 'sharedfrom', get_string('sharedfrom', 'tool_aibridge'), ['optional' => true]);
        $mform->addElement(
            'date_time_selector',
            'shareduntil',
            get_string('shareduntil', 'tool_aibridge'),
            ['optional' => true]
        );

        $this->add_action_buttons();
    }

    /**
     * Check the form.
     *
     * @param array $data
     * @param array $files
     * @return array field name => error.
     */
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);
        $id = (int) $data['id'];

        $shortname = trim($data['shortname']);
        if ($shortname !== '') {
            if (strpos($shortname, ':') !== false || !preg_match(template::SHORTNAME_PATTERN, $shortname)) {
                $errors['shortname'] = get_string('errorshortname', 'tool_aibridge');
            } else if (
                $DB->record_exists_select(
                    template::TABLE,
                    'shortname = :shortname AND id <> :id',
                    ['shortname' => $shortname, 'id' => $id]
                )
            ) {
                $errors['shortname'] = get_string('errorshortnameused', 'tool_aibridge');
            }
        }

        $parameters = trim($data['parameters']);
        if ($parameters !== '' && !is_array(json_decode($parameters, true))) {
            $errors['parameters'] = get_string('errorparameters', 'tool_aibridge');
        }

        if (isset($data['formatlinks'])) {
            $links = self::read_formatlinks($data['formatlinks']);
            if ($links === null) {
                $errors['formatlinks'] = get_string('errorformatlinks', 'tool_aibridge');
            } else {
                foreach ($links as $link) {
                    $format = template::find($link);
                    if (!$format || $format->get_type() !== template::TYPE_FORMAT) {
                        $errors['formatlinks'] = get_string('errornotaformat', 'tool_aibridge', $link);
                    }
                }
            }
        }

        if (!empty($data['parentid']) && $id) {
            // A prompt cannot extend itself, or one of the prompts that extend it.
            $parentid = (int) $data['parentid'];
            $depth = 0;
            while ($parentid && $depth++ < 10) {
                if ($parentid == $id) {
                    $errors['parentid'] = get_string('errorextendsloop', 'tool_aibridge', $data['name']);
                    break;
                }
                $parentid = (int) $DB->get_field(template::TABLE, 'parentid', ['id' => $parentid]);
            }
        }

        if (!empty($data['sharedfrom']) && !empty($data['shareduntil']) && $data['shareduntil'] < $data['sharedfrom']) {
            $errors['shareduntil'] = get_string('errorshareduntil', 'tool_aibridge');
        }
        return $errors;
    }

    /**
     * Read format links, one per line: "question type = format short name".
     *
     * @param string $text
     * @return array|null question type => short name, or null if a line cannot be read.
     */
    public static function read_formatlinks(string $text): ?array {
        $links = [];
        foreach (preg_split('/[\r\n]+/', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (!preg_match('/^([a-z0-9_*]+)\s*=\s*(\S+)$/', $line, $match)) {
                return null;
            }
            $links[$match[1]] = $match[2];
        }
        return $links;
    }

    /**
     * Write format links, one per line.
     *
     * @param array $links question type => short name.
     * @return string
     */
    public static function write_formatlinks(array $links): string {
        $lines = [];
        foreach ($links as $qtype => $shortname) {
            $lines[] = "$qtype = $shortname";
        }
        return implode("\n", $lines);
    }
}
