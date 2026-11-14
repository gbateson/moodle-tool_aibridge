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
 * The form to add a fine-tuning job.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tuning_form extends \moodleform {
    /**
     * Define the form.
     */
    protected function definition() {
        $mform = $this->_form;
        $plugin = 'tool_aibridge';
        $mform->addElement('hidden', 'contextid', $this->_customdata['contextid']);
        $mform->setType('contextid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('name'), ['size' => 40]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('select', 'providerid', get_string('tuningprovider', $plugin), $this->_customdata['providers']);
        $mform->addHelpButton('providerid', 'tuningprovider', $plugin);

        $mform->addElement('text', 'basemodel', get_string('basemodel', $plugin), ['size' => 40]);
        $mform->setType('basemodel', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('basemodel', 'basemodel', $plugin);

        // Moodle does not know the .jsonl file type, so any file is accepted, and its lines are checked.
        $mform->addElement(
            'filepicker',
            'trainingfile',
            get_string('trainingfile', $plugin),
            null,
            ['accepted_types' => '*', 'maxbytes' => 52428800]
        );
        $mform->addRule('trainingfile', null, 'required', null, 'client');
        $mform->addHelpButton('trainingfile', 'trainingfile', $plugin);

        $options = $this->_customdata['sharingoptions'];
        $mform->addElement('select', 'sharecontextid', get_string('tuningsharewith', $plugin), $options);
        $mform->addHelpButton('sharecontextid', 'tuningsharewith', $plugin);

        $this->add_action_buttons(true, get_string('addtuning', $plugin));
    }

    /**
     * Check the training file: each line must be JSON with "messages".
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $USER;
        $errors = parent::validation($data, $files);
        // get_file_content() would check the form again, so read the draft file here.
        $content = '';
        $usercontext = \context_user::instance($USER->id);
        $draftfiles = get_file_storage()->get_area_files($usercontext->id, 'user', 'draft', (int) $data['trainingfile'], 'id', false);
        if ($draftfiles) {
            $content = reset($draftfiles)->get_content();
        }
        $count = 0;
        foreach (preg_split('/\r?\n/', $content) as $number => $line) {
            if (trim($line) === '') {
                continue;
            }
            $example = json_decode($line, true);
            if (!is_array($example) || empty($example['messages']) || !is_array($example['messages'])) {
                $errors['trainingfile'] = get_string('errortrainingline', 'tool_aibridge', $number + 1);
                break;
            }
            $count++;
        }
        if (empty($errors['trainingfile']) && $count < 10) {
            $errors['trainingfile'] = get_string('errortrainingcount', 'tool_aibridge', 10);
        }
        return $errors;
    }
}
