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
 * Test data generator for the AI bridge.
 *
 * Other plugins can use it to add providers in their own tests:
 *
 *     $generator = $this->getDataGenerator()->get_plugin_generator('tool_aibridge');
 *     $provider = $generator->create_provider(['capability' => 'speech']);
 *     \curl::mock_response('ID3...');
 *
 * @package    tool_aibridge
 * @category   test
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_aibridge_generator extends component_generator_base {
    /** @var int the number of providers made so far. */
    protected $count = 0;

    /**
     * Reset the count.
     */
    public function reset() {
        $this->count = 0;
    }

    /**
     * Add a provider. By default, it is an OpenAI-style text provider
     * for the whole site, owned by the admin, and the AI policy is not required.
     *
     * @param array|stdClass $record fields to set, and optionally "apikey" and "preset".
     * @return \tool_aibridge\provider
     */
    public function create_provider($record = []): \tool_aibridge\provider {
        global $USER;
        $this->count++;
        $record = (array) $record;
        $preset = isset($record['preset']) ? $record['preset'] : '';
        if ($preset === '') {
            $capability = isset($record['capability']) ? $record['capability'] : \tool_aibridge\provider::CAPABILITY_TEXT;
            $preset = ($capability == \tool_aibridge\provider::CAPABILITY_SPEECH) ? 'openaitts' : 'openai';
            if ($capability == \tool_aibridge\provider::CAPABILITY_MULTIMODAL) {
                $preset = 'openaifiles';
            }
        }
        $apikey = isset($record['apikey']) ? $record['apikey'] : 'test-key-' . $this->count;
        unset($record['preset'], $record['apikey']);
        $defaults = \tool_aibridge\presets::get_presets()[$preset];
        $defaults['name'] = 'Provider ' . $this->count;
        $defaults['ownerid'] = !empty($USER->id) ? $USER->id : get_admin()->id;
        $defaults['contextid'] = SYSCONTEXTID;
        $defaults['enabled'] = 1;
        $data = (object) array_merge($defaults, $record);
        return \tool_aibridge\provider::save($data, $apikey);
    }
}
