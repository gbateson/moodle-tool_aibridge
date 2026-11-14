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

namespace tool_aibridge;

use tool_aibridge\library\parser;
use tool_aibridge\library\renderer;
use tool_aibridge\library\template;

/**
 * The library of prompts, output formats and fragments.
 *
 * Plugins ship templates in db/aibridge_templates.php, which sets
 * $templates to an array of key => definition. The short name of each is
 * "component:key". A definition has: type, name, and optionally description,
 * qformat, body, instructions, examples, parameters, formatlinks and extends.
 * In formatlinks and extends, a key without a colon is in the same plugin.
 *
 * Example:
 *
 *     $rendering = \tool_aibridge\library::render('mod_vocab:toeic-part2', $context, $USER->id,
 *             ['vocabulary-item' => 'reschedule', 'number-of-questions' => 4], 'multichoice');
 *     list($response, $parsed) = \tool_aibridge\library::generate($rendering, $context, $USER->id, 'mod_vocab');
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class library {
    /** The file in which plugins ship templates. */
    const DEFINITIONS_FILE = 'db/aibridge_templates.php';

    /**
     * Add or update the templates that every plugin ships.
     */
    public static function sync_all(): void {
        // The bridge's own templates come first, because other plugins' prompts extend them.
        self::sync_component('tool_aibridge');
        foreach (\core_component::get_plugin_types() as $type => $unused) {
            foreach (\core_component::get_plugin_list_with_file($type, self::DEFINITIONS_FILE) as $plugin => $file) {
                if ($type . '_' . $plugin !== 'tool_aibridge') {
                    self::sync_component($type . '_' . $plugin);
                }
            }
        }
    }

    /**
     * Add or update the templates that one plugin ships. Plugins can call this in their install and upgrade steps.
     *
     * @param string $component e.g. "mod_vocab".
     */
    public static function sync_component(string $component): void {
        $dir = \core_component::get_component_directory($component);
        $templates = [];
        if ($dir && file_exists($dir . '/' . self::DEFINITIONS_FILE)) {
            include($dir . '/' . self::DEFINITIONS_FILE);
        }
        self::sync_definitions($component, $templates);
    }

    /**
     * Add or update a plugin's templates from their definitions.
     * Templates that are no longer defined are disabled.
     *
     * @param string $component
     * @param array $definitions key => definition.
     */
    public static function sync_definitions(string $component, array $definitions): void {
        global $DB;
        $prefix = $component . ':';
        $expand = function ($shortname) use ($prefix) {
            return (strpos($shortname, ':') === false) ? $prefix . $shortname : $shortname;
        };

        // Fragments and formats first, then prompts, so that parents usually exist already.
        $order = [template::TYPE_FRAGMENT => 0, template::TYPE_FORMAT => 1, template::TYPE_PROMPT => 2];
        uasort($definitions, function ($a, $b) use ($order) {
            if ($a['type'] != $b['type']) {
                return $order[$a['type']] - $order[$b['type']];
            }
            return (empty($a['extends']) ? 0 : 1) - (empty($b['extends']) ? 0 : 1);
        });

        $ids = [];
        $parents = [];
        foreach ($definitions as $key => $definition) {
            $shortname = $prefix . $key;
            $data = (object) [
                'shortname' => $shortname,
                'type' => $definition['type'],
                'name' => $definition['name'],
                'component' => $component,
                'ownerid' => 0,
                'contextid' => SYSCONTEXTID,
                'enabled' => 1,
            ];
            foreach (['description', 'qformat', 'body', 'instructions', 'examples'] as $field) {
                $data->$field = isset($definition[$field]) ? trim($definition[$field]) : '';
            }
            $data->parameters = isset($definition['parameters']) ? $definition['parameters'] : [];
            $links = [];
            if (isset($definition['formatlinks'])) {
                foreach ($definition['formatlinks'] as $qtype => $link) {
                    $links[$qtype] = $expand($link);
                }
            }
            $data->formatlinks = $links;
            if (!empty($definition['extends'])) {
                $parents[$shortname] = $expand($definition['extends']);
            }
            $existing = $DB->get_record(template::TABLE, ['shortname' => $shortname]);
            $data->parentid = $existing ? $existing->parentid : 0;
            if (isset($parents[$shortname]) && ($parent = template::find($parents[$shortname]))) {
                $data->parentid = $parent->get_id();
            }
            if ($existing) {
                $data->id = $existing->id;
            }
            $ids[$shortname] = template::save($data, 0)->get_id();
        }

        // Link the prompts whose parents came later.
        foreach ($ids as $shortname => $id) {
            $parentid = 0;
            if (isset($parents[$shortname]) && ($parent = template::find($parents[$shortname]))) {
                $parentid = $parent->get_id();
            }
            if ($parentid != $DB->get_field(template::TABLE, 'parentid', ['id' => $id])) {
                template::save((object) ['id' => $id, 'parentid' => $parentid], 0);
            }
        }

        // Disable the templates that the plugin no longer ships.
        foreach ($DB->get_records(template::TABLE, ['component' => $component]) as $record) {
            if (!isset($ids[$record->shortname]) && $record->enabled) {
                $DB->set_field(template::TABLE, 'enabled', 0, ['id' => $record->id]);
            }
        }
    }

    /**
     * Find a template that a user can use in a context.
     *
     * @param string $shortname
     * @param \context $context
     * @param int $userid
     * @return template|null
     */
    public static function find(string $shortname, \context $context, int $userid): ?template {
        $template = template::find($shortname);
        if ($template && $template->can_use($context, $userid)) {
            return $template;
        }
        return null;
    }

    /**
     * Render a prompt.
     *
     * @param string|template $prompt the prompt, or its short name.
     * @param \context $context where the prompt is used.
     * @param int $userid who uses it.
     * @param array $values parameter name => value. "format" chooses a format by short name.
     * @param string $qtype the question type, e.g. "multichoice", which chooses the format.
     * @return library\rendering
     * @throws \moodle_exception if the prompt cannot be found or used.
     */
    public static function render(
        $prompt,
        \context $context,
        int $userid,
        array $values = [],
        string $qtype = ''
    ): library\rendering {
        if (is_string($prompt)) {
            $shortname = $prompt;
            $prompt = self::find($shortname, $context, $userid);
            if (!$prompt) {
                throw new \moodle_exception('errortemplatenotfound', 'tool_aibridge', '', $shortname);
            }
        } else if (!$prompt->can_use($context, $userid)) {
            throw new \moodle_exception('errortemplatenotfound', 'tool_aibridge', '', $prompt->get_shortname());
        }
        $renderer = new renderer(function ($shortname) use ($context, $userid) {
            return self::find($shortname, $context, $userid);
        });
        return $renderer->render($prompt, $values, $qtype);
    }

    /**
     * Send a rendered prompt to the AI, and read the reply.
     *
     * @param library\rendering $rendering
     * @param \context $context
     * @param int $userid
     * @param string $component the plugin that sends the prompt, for the log.
     * @param int $providerid one provider to use, or 0 for any.
     * @return array [response $response, library\parsed|null $parsed]
     */
    public static function generate(
        library\rendering $rendering,
        \context $context,
        int $userid,
        string $component = '',
        int $providerid = 0
    ): array {
        $prompt = $rendering->get_prompt();
        if (!$rendering->is_valid()) {
            $errors = $rendering->get_errors();
            return [response::failure('templateinvalid', $errors ? implode(' ', $errors) : ''), null];
        }
        $action = new action\generate_text($context->id, $userid, $rendering->get_text());
        $action->set_purpose($component, $prompt->get_shortname());
        $action->set_template($prompt->get_id(), $prompt->get_version());
        $response = manager::process($action, $providerid);
        if (!$response->get_success()) {
            return [$response, null];
        }
        return [$response, parser::parse($response->get_content(), $rendering->get_qformat())];
    }

    /**
     * Get templates as JSON, for export. API keys are never in templates.
     *
     * @param template[] $templates
     * @return string
     */
    public static function export(array $templates): string {
        $definitions = [];
        foreach ($templates as $template) {
            $definitions[] = $template->export();
        }
        return json_encode(
            ['tool_aibridge_templates' => $definitions],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Add templates from JSON. Each becomes the user's own template. A short name
     * that is already used gets a number added; links between the imported
     * templates follow the new names.
     *
     * @param string $json
     * @param int $contextid where the templates are shared.
     * @param int $userid the owner.
     * @return template[] the new templates.
     * @throws \moodle_exception if the JSON is not a template export.
     */
    public static function import(string $json, int $contextid, int $userid): array {
        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !isset($decoded['tool_aibridge_templates']) || !is_array($decoded['tool_aibridge_templates'])) {
            throw new \moodle_exception('errorimport', 'tool_aibridge');
        }
        $definitions = $decoded['tool_aibridge_templates'];
        $types = template::get_type_options();
        $renamed = [];
        $templates = [];
        foreach ($definitions as $definition) {
            if (
                !is_array($definition) || empty($definition['name']) || empty($definition['type'])
                    || !isset($types[$definition['type']])
            ) {
                throw new \moodle_exception('errorimport', 'tool_aibridge');
            }
            $old = isset($definition['shortname']) ? (string) $definition['shortname'] : '';
            $base = ($old !== '') ? preg_replace('/^.*:/', '', $old) : $definition['name'];
            $data = (object) [
                'shortname' => template::make_shortname($base),
                'type' => $definition['type'],
                'name' => clean_param($definition['name'], PARAM_TEXT),
                'component' => '',
                'ownerid' => $userid,
                'contextid' => $contextid,
                'enabled' => 1,
                'parentid' => 0,
            ];
            foreach (['description', 'body', 'instructions', 'examples'] as $field) {
                $data->$field = isset($definition[$field]) ? (string) $definition[$field] : '';
            }
            $qformat = isset($definition['qformat']) ? (string) $definition['qformat'] : '';
            $data->qformat = in_array($qformat, template::QFORMATS) ? $qformat : '';
            $data->parameters = isset($definition['parameters']) && is_array($definition['parameters'])
                    ? $definition['parameters'] : [];
            $data->formatlinks = isset($definition['formatlinks']) && is_array($definition['formatlinks'])
                    ? $definition['formatlinks'] : [];
            $template = template::save($data, $userid);
            if ($old !== '') {
                $renamed[$old] = $template->get_shortname();
            }
            $templates[$template->get_id()] = [$template, $definition];
        }

        // Follow the new names in links, extends and includes.
        $result = [];
        foreach ($templates as $id => [$template, $definition]) {
            $data = (object) ['id' => $id];
            $links = [];
            foreach ($template->get_formatlinks() as $qtype => $link) {
                $links[$qtype] = isset($renamed[$link]) ? $renamed[$link] : $link;
            }
            $data->formatlinks = $links;
            if (!empty($definition['extends'])) {
                $parentname = isset($renamed[$definition['extends']]) ? $renamed[$definition['extends']] : $definition['extends'];
                if ($parent = template::find($parentname)) {
                    $data->parentid = $parent->get_id();
                }
            }
            foreach (['body', 'instructions', 'examples'] as $field) {
                $data->$field = preg_replace_callback(renderer::INCLUDE_PATTERN, function ($match) use ($renamed) {
                    return isset($renamed[$match[1]]) ? '{{include:' . $renamed[$match[1]] . '}}' : $match[0];
                }, (string) $template->get_record()->$field);
            }
            $result[$id] = template::save($data, $userid);
        }
        // Each imported template starts at version 1.
        foreach ($result as $id => $template) {
            self::reset_versions($template, $userid);
            $result[$id] = template::get($id);
        }
        return $result;
    }

    /**
     * Make the current content of a new template its only version.
     *
     * @param template $template
     * @param int $userid
     */
    protected static function reset_versions(template $template, int $userid): void {
        global $DB;
        $DB->delete_records(template::VERSIONS_TABLE, ['templateid' => $template->get_id()]);
        $DB->set_field(template::TABLE, 'version', 1, ['id' => $template->get_id()]);
        $version = (object) ['templateid' => $template->get_id(), 'version' => 1, 'userid' => $userid, 'timecreated' => time()];
        foreach (template::VERSIONED_FIELDS as $field) {
            $version->$field = $template->get_record()->$field;
        }
        $DB->insert_record(template::VERSIONS_TABLE, $version);
    }
}
