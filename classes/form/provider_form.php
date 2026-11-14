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

use tool_aibridge\presets;
use tool_aibridge\provider;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * The form to add or edit an AI provider.
 *
 * The API key is never sent to the browser: the key field is always empty,
 * and is left empty to keep the current key.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider_form extends \moodleform {
    /**
     * Define the form.
     */
    protected function definition() {
        $mform = $this->_form;
        $sharingoptions = $this->_customdata['sharingoptions'];
        $haskey = !empty($this->_customdata['haskey']);
        $isnew = empty($this->_customdata['id']);

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        // The context of the page, not the context in which the provider is shared.
        $mform->addElement('hidden', 'contextid', $this->_customdata['contextid']);
        $mform->setType('contextid', PARAM_INT);

        if ($isnew) {
            $mform->addElement('header', 'presetheader', get_string('preset', 'tool_aibridge'));
            // The button must be registered before it is made, so that it skips the form's checks.
            $mform->registerNoSubmitButton('applypreset');
            $group = [
                $mform->createElement('select', 'preset', '', presets::get_menu()),
                $mform->createElement('submit', 'applypreset', get_string('applypreset', 'tool_aibridge')),
            ];
            $mform->addGroup($group, 'presetgroup', get_string('preset', 'tool_aibridge'), ' ', false);
            $mform->addHelpButton('presetgroup', 'preset', 'tool_aibridge');
        }

        $mform->addElement('header', 'generalheader', get_string('general'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => 40]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement(
            'select',
            'capability',
            get_string('capability', 'tool_aibridge'),
            provider::get_capability_options()
        );
        $mform->addHelpButton('capability', 'capability', 'tool_aibridge');

        $mform->addElement('select', 'sharecontextid', get_string('sharewith', 'tool_aibridge'), $sharingoptions);
        $mform->addHelpButton('sharecontextid', 'sharewith', 'tool_aibridge');

        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'tool_aibridge'));
        $mform->setDefault('enabled', 1);

        $mform->addElement('header', 'connectionheader', get_string('connection', 'tool_aibridge'));
        $mform->setExpanded('connectionheader');

        $mform->addElement('text', 'url', get_string('url', 'tool_aibridge'), ['size' => 60]);
        $mform->setType('url', PARAM_RAW_TRIMMED);
        $mform->addRule('url', null, 'required', null, 'client');
        $mform->addHelpButton('url', 'url', 'tool_aibridge');

        $mform->addElement('text', 'model', get_string('model', 'tool_aibridge'), ['size' => 40]);
        $mform->setType('model', PARAM_TEXT);
        $mform->addHelpButton('model', 'model', 'tool_aibridge');

        $label = get_string($haskey ? 'apikeykeep' : 'apikey', 'tool_aibridge');
        $mform->addElement('password', 'apikey', $label, ['size' => 40, 'autocomplete' => 'new-password']);
        $mform->setType('apikey', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('apikey', 'apikey', 'tool_aibridge');
        if ($haskey) {
            $mform->addElement('advcheckbox', 'removekey', get_string('removekey', 'tool_aibridge'));
        }

        $mform->addElement('textarea', 'headers', get_string('headers', 'tool_aibridge'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('headers', PARAM_RAW);
        $mform->addHelpButton('headers', 'headers', 'tool_aibridge');

        $mform->addElement(
            'textarea',
            'requesttemplate',
            get_string('requesttemplate', 'tool_aibridge'),
            ['rows' => 12, 'cols' => 60, 'style' => 'font-family: monospace']
        );
        $mform->setType('requesttemplate', PARAM_RAW);
        $mform->addRule('requesttemplate', null, 'required', null, 'client');
        $mform->addHelpButton('requesttemplate', 'requesttemplate', 'tool_aibridge');

        $mform->addElement(
            'textarea',
            'filetemplate',
            get_string('filetemplate', 'tool_aibridge'),
            ['rows' => 6, 'cols' => 60, 'style' => 'font-family: monospace']
        );
        $mform->setType('filetemplate', PARAM_RAW);
        $mform->addHelpButton('filetemplate', 'filetemplate', 'tool_aibridge');
        $mform->hideIf('filetemplate', 'capability', 'neq', provider::CAPABILITY_MULTIMODAL);

        $mform->addElement('text', 'returnpath', get_string('returnpath', 'tool_aibridge'), ['size' => 60]);
        $mform->setType('returnpath', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('returnpath', 'returnpath', 'tool_aibridge');

        $mform->addElement(
            'textarea',
            'tokendefaults',
            get_string('tokendefaults', 'tool_aibridge'),
            ['rows' => 3, 'cols' => 60, 'style' => 'font-family: monospace']
        );
        $mform->setType('tokendefaults', PARAM_RAW);
        $mform->addHelpButton('tokendefaults', 'tokendefaults', 'tool_aibridge');

        $mform->addElement('header', 'safeguardsheader', get_string('safeguards', 'tool_aibridge'));

        $mform->addElement('textarea', 'datanotice', get_string('datanotice', 'tool_aibridge'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('datanotice', PARAM_TEXT);
        $mform->addHelpButton('datanotice', 'datanotice', 'tool_aibridge');

        $mform->addElement('text', 'userratelimit', get_string('userratelimit', 'tool_aibridge'), ['size' => 6]);
        $mform->setType('userratelimit', PARAM_INT);
        $mform->setDefault('userratelimit', 0);
        $mform->addHelpButton('userratelimit', 'userratelimit', 'tool_aibridge');

        $mform->addElement('date_time_selector', 'sharedfrom', get_string('sharedfrom', 'tool_aibridge'), ['optional' => true]);
        $mform->addElement(
            'date_time_selector',
            'shareduntil',
            get_string('shareduntil', 'tool_aibridge'),
            ['optional' => true]
        );

        $mform->addElement('text', 'sortorder', get_string('sortorder', 'tool_aibridge'), ['size' => 6]);
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);
        $mform->addHelpButton('sortorder', 'sortorder', 'tool_aibridge');

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
        $errors = parent::validation($data, $files);

        $url = trim($data['url']);
        if (!preg_match('#^https?://[^/\s]+#i', $url)) {
            $errors['url'] = get_string('errorurl', 'tool_aibridge');
        }
        foreach (['headers', 'tokendefaults'] as $name) {
            $value = trim($data[$name] ?? '');
            if ($value !== '' && !is_array(json_decode($value, true))) {
                $errors[$name] = get_string('errorjsonobject', 'tool_aibridge');
            }
        }
        foreach (['requesttemplate', 'filetemplate'] as $name) {
            // A hidden field, e.g. the file template of a text provider, is not sent.
            $value = trim($data[$name] ?? '');
            if ($value !== '' && !self::is_json_template($value)) {
                $errors[$name] = get_string('errorjsontemplate', 'tool_aibridge');
            }
        }
        if (!empty($data['sharedfrom']) && !empty($data['shareduntil']) && $data['shareduntil'] < $data['sharedfrom']) {
            $errors['shareduntil'] = get_string('errorshareduntil', 'tool_aibridge');
        }
        if ($data['userratelimit'] < 0) {
            $errors['userratelimit'] = get_string('errornegative', 'tool_aibridge');
        }
        return $errors;
    }

    /**
     * Is a template valid JSON, once its tokens are replaced?
     *
     * @param string $template
     * @return bool
     */
    public static function is_json_template(string $template): bool {
        $json = str_replace('{{FILE_PARTS}}', '', $template);
        // A token inside a string stays a string; a token on its own, e.g. {{MAXTOKENS}}, becomes a number.
        $json = preg_replace('/\{\{[A-Za-z0-9_]+\}\}/', '1', $json);
        json_decode($json);
        return (json_last_error() === JSON_ERROR_NONE);
    }
}
