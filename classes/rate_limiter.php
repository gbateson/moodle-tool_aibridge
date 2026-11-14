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
 * Limit how many requests are sent in an hour: by everyone, by one user, and by one user to one provider.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rate_limiter {
    /** The period of the limits, in seconds. */
    const PERIOD = HOURSECS;

    /**
     * Is a user within the site's limits?
     *
     * @param int $userid
     * @return string '' if the user is within the limits, or the error code.
     */
    public static function check_site(int $userid): string {
        global $DB;
        $since = time() - self::PERIOD;
        $limit = (int) get_config('tool_aibridge', 'siteratelimit');
        if ($limit > 0) {
            $count = $DB->count_records_select(logger::TABLE, 'timecreated > :since', ['since' => $since]);
            if ($count >= $limit) {
                return 'siteratelimit';
            }
        }
        $limit = (int) get_config('tool_aibridge', 'userratelimit');
        if ($limit > 0) {
            $count = $DB->count_records_select(
                logger::TABLE,
                'userid = :userid AND timecreated > :since',
                ['userid' => $userid, 'since' => $since]
            );
            if ($count >= $limit) {
                return 'userratelimit';
            }
        }
        return '';
    }

    /**
     * Is a user within a provider's limit?
     *
     * @param provider $provider
     * @param int $userid
     * @return bool
     */
    public static function check_provider(provider $provider, int $userid): bool {
        global $DB;
        $limit = (int) $provider->get_record()->userratelimit;
        if ($limit <= 0 || $provider->get_record()->ownerid == $userid) {
            return true;
        }
        $count = $DB->count_records_select(
            logger::TABLE,
            'userid = :userid AND providerid = :providerid AND timecreated > :since',
            ['userid' => $userid, 'providerid' => $provider->get_id(), 'since' => time() - self::PERIOD]
        );
        return ($count < $limit);
    }
}
