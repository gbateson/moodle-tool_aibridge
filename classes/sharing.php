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

/**
 * Sharing rules for things that users own and share in a context, such as providers and templates.
 *
 * A record has an ownerid, a contextid (the site, a course category, a
 * course, or the owner's user context for "only me"), an enabled flag, and
 * sharedfrom and shareduntil times (0 for no limit).
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sharing {
    /**
     * Is a record enabled, and shared at this time?
     *
     * @param \stdClass $record
     * @param int|null $time
     * @return bool
     */
    public static function is_active(\stdClass $record, ?int $time = null): bool {
        if ($time === null) {
            $time = time();
        }
        if (empty($record->enabled)) {
            return false;
        }
        if (!empty($record->sharedfrom) && $time < $record->sharedfrom) {
            return false;
        }
        if (!empty($record->shareduntil) && $time > $record->shareduntil) {
            return false;
        }
        return true;
    }

    /**
     * Can a user use a record in a context?
     *
     * @param \stdClass $record
     * @param \context $context where it is used.
     * @param int $userid
     * @param string $usecap the capability to use shared records.
     * @param bool $allowshared may records be shared below the site level?
     * @return bool
     */
    public static function can_use(
        \stdClass $record,
        \context $context,
        int $userid,
        string $usecap,
        bool $allowshared = true
    ): bool {
        if (!self::is_active($record)) {
            return false;
        }
        if ($record->ownerid && $record->ownerid == $userid) {
            return true;
        }
        $recordcontext = \context::instance_by_id($record->contextid, IGNORE_MISSING);
        if (!$recordcontext || $recordcontext->contextlevel == CONTEXT_USER) {
            return false;
        }
        if ($recordcontext->contextlevel != CONTEXT_SYSTEM && !$allowshared) {
            return false;
        }
        if (!in_array($recordcontext->id, $context->get_parent_context_ids(true))) {
            return false;
        }
        return has_capability($usecap, $context, $userid);
    }

    /**
     * Get the ids of the contexts whose records could be used in a context: the context,
     * its parents, and the user's own context.
     *
     * @param \context $context
     * @param int $userid
     * @return int[]
     */
    public static function get_contextids(\context $context, int $userid): array {
        $contextids = $context->get_parent_context_ids(true);
        $contextids[] = \context_user::instance($userid)->id;
        return $contextids;
    }

    /**
     * Get the contexts in which a user may share a record, from a context.
     *
     * @param \context $context the context of the management page.
     * @param int $userid
     * @param string $addcap the capability to share in a course or category.
     * @param string $managecap the capability to share with the whole site.
     * @param bool $allowshared may records be shared below the site level?
     * @return string[] context id => name.
     */
    public static function get_options(
        \context $context,
        int $userid,
        string $addcap,
        string $managecap,
        bool $allowshared = true
    ): array {
        $options = [\context_user::instance($userid)->id => get_string('shareonlyme', 'tool_aibridge')];
        if ($allowshared) {
            foreach (array_reverse($context->get_parent_contexts(true)) as $parent) {
                if ($parent->contextlevel == CONTEXT_COURSE || $parent->contextlevel == CONTEXT_COURSECAT) {
                    if (has_capability($addcap, $parent, $userid)) {
                        $options[$parent->id] = $parent->get_context_name();
                    }
                }
            }
        }
        $system = \context_system::instance();
        if (has_capability($managecap, $system, $userid)) {
            $options[$system->id] = get_string('sharesite', 'tool_aibridge');
        }
        return $options;
    }

    /**
     * Get a description of where a record is shared.
     *
     * @param \stdClass $record
     * @return string
     */
    public static function get_description(\stdClass $record): string {
        $context = \context::instance_by_id($record->contextid, IGNORE_MISSING);
        if (!$context) {
            return '?';
        }
        if ($context->contextlevel == CONTEXT_USER) {
            return get_string('shareonlyowner', 'tool_aibridge');
        }
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            return get_string('sharesite', 'tool_aibridge');
        }
        return $context->get_context_name();
    }

    /**
     * Sort records: the user's own first, then those shared nearest to the context, then by sort order.
     * Each record must have a "contextdepth" field.
     *
     * @param \stdClass[] $records
     * @param int $userid
     * @return \stdClass[]
     */
    public static function sort(array $records, int $userid): array {
        uasort($records, function ($a, $b) use ($userid) {
            $aown = ($a->ownerid == $userid) ? 0 : 1;
            $bown = ($b->ownerid == $userid) ? 0 : 1;
            if ($aown != $bown) {
                return $aown - $bown;
            }
            if ($a->contextdepth != $b->contextdepth) {
                return $b->contextdepth - $a->contextdepth;
            }
            $asort = isset($a->sortorder) ? $a->sortorder : 0;
            $bsort = isset($b->sortorder) ? $b->sortorder : 0;
            if ($asort != $bsort) {
                return $asort - $bsort;
            }
            return $a->id - $b->id;
        });
        return $records;
    }

    /**
     * A course or category is about to be deleted. The templates shared there become
     * their owners' own templates; the providers and fine-tuning jobs shared there are
     * deleted, since they hold API keys that were only shared for that place.
     *
     * @param \context $context
     */
    public static function context_deleted(\context $context): void {
        global $DB;
        $providers = $DB->get_records(provider::TABLE, ['contextid' => $context->id], '', 'id');
        foreach ($providers as $record) {
            provider::get($record->id)->delete();
        }
        foreach ($DB->get_records(tuning::TABLE, ['contextid' => $context->id], '', 'id') as $record) {
            tuning::get($record->id)->delete();
        }
        $templates = $DB->get_records(library\template::TABLE, ['contextid' => $context->id], '', 'id, ownerid');
        foreach ($templates as $record) {
            $owner = $record->ownerid ? \context_user::instance($record->ownerid, IGNORE_MISSING) : null;
            $contextid = $owner ? $owner->id : SYSCONTEXTID;
            $DB->set_field(library\template::TABLE, 'contextid', $contextid, ['id' => $record->id]);
        }
    }

    /**
     * A user was deleted: delete their providers, fine-tuning jobs and personal templates.
     * Templates that they shared are kept for the people who use them.
     *
     * @param \core\event\user_deleted $event
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        global $DB;
        $userid = $event->objectid;
        $contextid = $event->contextid;
        $DB->delete_records(provider::TABLE, ['ownerid' => $userid]);
        foreach ($DB->get_records(tuning::TABLE, ['ownerid' => $userid], '', 'id') as $record) {
            tuning::get($record->id)->delete();
        }
        foreach ($DB->get_records(library\template::TABLE, ['ownerid' => $userid]) as $record) {
            if ($record->contextid == $contextid) {
                $DB->delete_records(library\template::VERSIONS_TABLE, ['templateid' => $record->id]);
                $DB->set_field(library\template::TABLE, 'parentid', 0, ['parentid' => $record->id]);
                $DB->delete_records(library\template::TABLE, ['id' => $record->id]);
            } else {
                $DB->set_field(library\template::TABLE, 'ownerid', 0, ['id' => $record->id]);
            }
        }
        $DB->delete_records(policy::TABLE, ['userid' => $userid]);
    }
}
