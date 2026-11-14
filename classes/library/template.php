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

namespace tool_aibridge\library;

use tool_aibridge\sharing;

/**
 * A template in the library: a prompt, an output format, or a fragment.
 *
 * - A prompt is the text sent to the AI. It can extend another prompt, by
 *   replacing the parent's {{block:name}}...{{endblock}} sections, and it
 *   links each question type to a format.
 * - A format describes the output the AI must give, e.g. a GIFT template
 *   with instructions and examples. It says how to read the reply (qformat).
 * - A fragment is text that prompts and formats share with {{include:shortname}}.
 *
 * Any template can declare parameters, e.g. {{target-language}}, with defaults.
 * Templates that plugins ship are read-only; users copy them to change them.
 * Each change makes a new version, and requests record the version they used.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template {
    /** A prompt. */
    const TYPE_PROMPT = 'prompt';

    /** An output format. */
    const TYPE_FORMAT = 'format';

    /** A fragment of text, used by prompts and formats. */
    const TYPE_FRAGMENT = 'fragment';

    /** The output formats that can be read. */
    const QFORMATS = ['gift', 'multianswer', 'xml', 'json', 'text'];

    /** The table of templates. */
    const TABLE = 'tool_aibridge_templates';

    /** The table of versions. */
    const VERSIONS_TABLE = 'tool_aibridge_tpl_versions';

    /** The fields whose changes make a new version. */
    const VERSIONED_FIELDS = ['name', 'qformat', 'body', 'instructions', 'examples', 'parameters', 'formatlinks', 'parentid'];

    /** The pattern of a short name, e.g. "mod_vocab:toeic-part1" or "my-prompt". */
    const SHORTNAME_PATTERN = '/^[a-z0-9_]+(:[a-z0-9_.-]+)?$|^[a-z0-9][a-z0-9_.-]*$/';

    /** @var \stdClass the record from the templates table. */
    protected $record;

    /**
     * Create a template from its record.
     *
     * @param \stdClass $record
     */
    public function __construct(\stdClass $record) {
        $this->record = $record;
    }

    /**
     * Get a template by its id.
     *
     * @param int $id
     * @return template
     */
    public static function get(int $id): template {
        global $DB;
        return new template($DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST));
    }

    /**
     * Find a template by its short name. Access is not checked.
     *
     * @param string $shortname
     * @return template|null
     */
    public static function find(string $shortname): ?template {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['shortname' => $shortname]);
        return $record ? new template($record) : null;
    }

    /**
     * Get the types of template.
     *
     * @return string[] type => name.
     */
    public static function get_type_options(): array {
        $options = [];
        foreach ([self::TYPE_PROMPT, self::TYPE_FORMAT, self::TYPE_FRAGMENT] as $type) {
            $options[$type] = get_string('templatetype_' . $type, 'tool_aibridge');
        }
        return $options;
    }

    /**
     * Get the output formats that can be read.
     *
     * @return string[] qformat => name.
     */
    public static function get_qformat_options(): array {
        $options = [];
        foreach (self::QFORMATS as $qformat) {
            $options[$qformat] = get_string('qformat_' . $qformat, 'tool_aibridge');
        }
        return $options;
    }

    /**
     * Get the template's record.
     *
     * @return \stdClass
     */
    public function get_record(): \stdClass {
        return $this->record;
    }

    /**
     * Get the template's id.
     *
     * @return int
     */
    public function get_id(): int {
        return (int) $this->record->id;
    }

    /**
     * Get the template's short name.
     *
     * @return string
     */
    public function get_shortname(): string {
        return (string) $this->record->shortname;
    }

    /**
     * Get the template's name.
     *
     * @return string
     */
    public function get_name(): string {
        return (string) $this->record->name;
    }

    /**
     * Get the template's type: prompt, format or fragment.
     *
     * @return string
     */
    public function get_type(): string {
        return (string) $this->record->type;
    }

    /**
     * Get the template's version.
     *
     * @return int
     */
    public function get_version(): int {
        return (int) $this->record->version;
    }

    /**
     * Get the template's body.
     *
     * @return string
     */
    public function get_body(): string {
        return (string) $this->record->body;
    }

    /**
     * Is this a template that a plugin ships?
     *
     * @return bool
     */
    public function is_shipped(): bool {
        return ($this->record->component !== '' && $this->record->component !== null);
    }

    /**
     * Get the declared parameters.
     *
     * @return array name => [name, default, required, personal, description].
     */
    public function get_parameters(): array {
        return self::decode_parameters((string) $this->record->parameters);
    }

    /**
     * Read declared parameters from JSON: a list of objects, or an object of name => default.
     *
     * @param string $json
     * @return array name => [name, default, required, personal, description].
     */
    public static function decode_parameters(string $json): array {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $parameters = [];
        foreach ($decoded as $key => $value) {
            if (is_array($value)) {
                $name = isset($value['name']) ? (string) $value['name'] : (string) $key;
            } else {
                $name = (string) $key;
                $value = ['default' => $value];
            }
            if ($name === '' || is_numeric($name)) {
                continue;
            }
            $parameters[$name] = [
                'name' => $name,
                'default' => isset($value['default']) ? (string) $value['default'] : '',
                'required' => !empty($value['required']),
                'personal' => !empty($value['personal']),
                'description' => isset($value['description']) ? (string) $value['description'] : '',
            ];
        }
        return $parameters;
    }

    /**
     * Get the formats linked to each question type, e.g. "multichoice" => "tool_aibridge:gift-multichoice".
     * The key "*" is used for any question type.
     *
     * @return string[] question type => format short name.
     */
    public function get_formatlinks(): array {
        $links = json_decode((string) $this->record->formatlinks, true);
        if (!is_array($links)) {
            return [];
        }
        $result = [];
        foreach ($links as $qtype => $shortname) {
            if (is_string($shortname) && $shortname !== '') {
                $result[(string) $qtype] = $shortname;
            }
        }
        return $result;
    }

    /**
     * Get the prompt that this prompt extends.
     *
     * @return template|null
     */
    public function get_parent(): ?template {
        global $DB;
        if (empty($this->record->parentid)) {
            return null;
        }
        $record = $DB->get_record(self::TABLE, ['id' => $this->record->parentid]);
        return $record ? new template($record) : null;
    }

    /**
     * Can a user use this template in a context? Shipped templates can be used everywhere.
     *
     * @param \context $context
     * @param int $userid
     * @return bool
     */
    public function can_use(\context $context, int $userid): bool {
        return sharing::can_use($this->record, $context, $userid, 'tool/aibridge:usetemplate');
    }

    /**
     * Can a user change or delete this template? Shipped templates cannot be changed.
     *
     * @param int $userid
     * @return bool
     */
    public function can_edit(int $userid): bool {
        if ($this->is_shipped()) {
            return false;
        }
        if (has_capability('tool/aibridge:managetemplates', \context_system::instance(), $userid)) {
            return true;
        }
        return ($this->record->ownerid && $this->record->ownerid == $userid);
    }

    /**
     * Get the templates a user can use in a context, own first, then nearest.
     *
     * @param \context $context
     * @param int $userid
     * @param string $type '' for every type.
     * @return template[] id => template.
     */
    public static function get_available(\context $context, int $userid, string $type = ''): array {
        global $DB;
        [$ctxsql, $params] = $DB->get_in_or_equal(sharing::get_contextids($context, $userid), SQL_PARAMS_NAMED, 'ctx');
        $where = "t.enabled = 1 AND (t.ownerid = :ownerid OR t.contextid $ctxsql)";
        $params['ownerid'] = $userid;
        if ($type !== '') {
            $where .= ' AND t.type = :type';
            $params['type'] = $type;
        }
        $sql = "SELECT t.*, ctx.depth AS contextdepth
                   FROM {" . self::TABLE . "} t
                   JOIN {context} ctx ON ctx.id = t.contextid
                  WHERE $where";
        $records = [];
        foreach ($DB->get_records_sql($sql, $params) as $record) {
            if (sharing::can_use($record, $context, $userid, 'tool/aibridge:usetemplate')) {
                $records[$record->id] = $record;
            }
        }
        $templates = [];
        foreach (sharing::sort($records, $userid) as $id => $record) {
            $templates[$id] = new template($record);
        }
        return $templates;
    }

    /**
     * Get the templates to show in the library for a context: those that can be used there,
     * the user's own, and every template if the user manages templates and the context is the site.
     *
     * @param \context $context
     * @param int $userid
     * @return template[] id => template, sorted by type and name.
     */
    public static function get_manageable(\context $context, int $userid): array {
        global $DB;
        if (
            $context->contextlevel == CONTEXT_SYSTEM
                && has_capability('tool/aibridge:managetemplates', $context, $userid)
        ) {
            $records = $DB->get_records(self::TABLE);
        } else {
            $records = [];
            foreach (self::get_available($context, $userid) as $id => $template) {
                $records[$id] = $template->get_record();
            }
            foreach ($DB->get_records(self::TABLE, ['ownerid' => $userid]) as $id => $record) {
                $records[$id] = $record;
            }
        }
        $order = array_flip([self::TYPE_PROMPT, self::TYPE_FORMAT, self::TYPE_FRAGMENT]);
        uasort($records, function ($a, $b) use ($order) {
            $atype = isset($order[$a->type]) ? $order[$a->type] : 9;
            $btype = isset($order[$b->type]) ? $order[$b->type] : 9;
            if ($atype != $btype) {
                return $atype - $btype;
            }
            return strcmp($a->name, $b->name);
        });
        $templates = [];
        foreach ($records as $id => $record) {
            $templates[$id] = new template($record);
        }
        return $templates;
    }

    /**
     * Get the contexts in which a user may share a template, from a context.
     *
     * @param \context $context
     * @param int $userid
     * @return string[] context id => name.
     */
    public static function get_sharing_options(\context $context, int $userid): array {
        return sharing::get_options($context, $userid, 'tool/aibridge:addtemplate', 'tool/aibridge:managetemplates');
    }

    /**
     * Make a short name that no template has yet, from a name.
     *
     * @param string $name
     * @return string
     */
    public static function make_shortname(string $name): string {
        global $DB;
        $base = \core_text::strtolower(trim($name));
        $base = preg_replace('/[^a-z0-9]+/', '-', $base);
        $base = trim($base, '-');
        if ($base === '') {
            $base = 'template';
        }
        $base = substr($base, 0, 200);
        $shortname = $base;
        $i = 1;
        while ($DB->record_exists(self::TABLE, ['shortname' => $shortname])) {
            $i++;
            $shortname = $base . '-' . $i;
        }
        return $shortname;
    }

    /**
     * Add or update a template. A change to its content makes a new version.
     *
     * @param \stdClass $data the template's fields. If it has an id, the template is updated.
     * @param int $userid who made the change.
     * @return template
     */
    public static function save(\stdClass $data, int $userid): template {
        global $DB;
        $record = clone($data);
        $now = time();
        $record->timemodified = $now;
        foreach (['parameters', 'formatlinks'] as $field) {
            if (isset($record->$field) && is_array($record->$field)) {
                $record->$field = $record->$field ? json_encode(
                    $record->$field,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                ) : '';
            }
        }
        if (empty($record->id)) {
            if (empty($record->shortname)) {
                $record->shortname = self::make_shortname(isset($record->name) ? $record->name : '');
            }
            $record->version = 1;
            $record->timecreated = $now;
            $record->id = $DB->insert_record(self::TABLE, $record);
            $template = self::get($record->id);
            $template->save_version($userid);
            return $template;
        }

        $old = $DB->get_record(self::TABLE, ['id' => $record->id], '*', MUST_EXIST);
        $changed = false;
        foreach (self::VERSIONED_FIELDS as $field) {
            if (isset($record->$field) && (string) $record->$field !== (string) $old->$field) {
                $changed = true;
            }
        }
        if ($changed) {
            $record->version = $old->version + 1;
        } else {
            unset($record->version);
        }
        $DB->update_record(self::TABLE, $record);
        $template = self::get($record->id);
        if ($changed) {
            $template->save_version($userid);
        }
        return $template;
    }

    /**
     * Save the current content as a version.
     *
     * @param int $userid
     */
    protected function save_version(int $userid): void {
        global $DB;
        $version = (object) [
            'templateid' => $this->get_id(),
            'version' => $this->get_version(),
            'userid' => $userid,
            'timecreated' => time(),
        ];
        foreach (self::VERSIONED_FIELDS as $field) {
            $version->$field = $this->record->$field;
        }
        $DB->insert_record(self::VERSIONS_TABLE, $version);
    }

    /**
     * Get the versions of this template, newest first.
     *
     * @return \stdClass[]
     */
    public function get_versions(): array {
        global $DB;
        return $DB->get_records(self::VERSIONS_TABLE, ['templateid' => $this->get_id()], 'version DESC');
    }

    /**
     * Make an earlier version the current one. This makes a new version with the old content.
     *
     * @param int $version
     * @param int $userid
     * @return template
     */
    public function restore_version(int $version, int $userid): template {
        global $DB;
        $old = $DB->get_record(self::VERSIONS_TABLE, ['templateid' => $this->get_id(), 'version' => $version], '*', MUST_EXIST);
        $data = (object) ['id' => $this->get_id()];
        foreach (self::VERSIONED_FIELDS as $field) {
            $data->$field = $old->$field;
        }
        return self::save($data, $userid);
    }

    /**
     * Copy this template, so that a user can change it.
     *
     * @param int $contextid where the copy is shared.
     * @param int $userid the owner of the copy.
     * @return template
     */
    public function copy(int $contextid, int $userid): template {
        $data = clone($this->record);
        unset($data->id, $data->contextdepth);
        $data->name = get_string('copyof', 'tool_aibridge', $this->get_name());
        $data->shortname = self::make_shortname(preg_replace('/^.*:/', '', $this->get_shortname()));
        $data->component = '';
        $data->ownerid = $userid;
        $data->contextid = $contextid;
        $data->enabled = 1;
        $data->sharedfrom = 0;
        $data->shareduntil = 0;
        return self::save($data, $userid);
    }

    /**
     * Get the templates that extend this one.
     *
     * @return template[]
     */
    public function get_children(): array {
        global $DB;
        $templates = [];
        foreach ($DB->get_records(self::TABLE, ['parentid' => $this->get_id()]) as $record) {
            $templates[$record->id] = new template($record);
        }
        return $templates;
    }

    /**
     * Delete the template and its versions.
     *
     * @throws \moodle_exception if other prompts extend this one.
     */
    public function delete(): void {
        global $DB;
        if ($this->get_children()) {
            throw new \moodle_exception('errortemplatehaschildren', 'tool_aibridge', '', $this->get_name());
        }
        $DB->delete_records(self::VERSIONS_TABLE, ['templateid' => $this->get_id()]);
        $DB->delete_records(self::TABLE, ['id' => $this->get_id()]);
    }

    /**
     * Get the template as a definition, for export. The parent is given by short name.
     *
     * @return array
     */
    public function export(): array {
        $definition = [
            'shortname' => $this->get_shortname(),
            'type' => $this->get_type(),
            'name' => $this->get_name(),
        ];
        foreach (['description', 'qformat', 'body', 'instructions', 'examples'] as $field) {
            if ((string) $this->record->$field !== '') {
                $definition[$field] = (string) $this->record->$field;
            }
        }
        if ($parent = $this->get_parent()) {
            $definition['extends'] = $parent->get_shortname();
        }
        if ($parameters = $this->get_parameters()) {
            $definition['parameters'] = array_values($parameters);
        }
        if ($links = $this->get_formatlinks()) {
            $definition['formatlinks'] = $links;
        }
        return $definition;
    }
}
