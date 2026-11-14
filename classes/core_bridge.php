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
 * Talk to Moodle's own AI subsystem (Moodle 4.5 and later), if there is one.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_bridge {
    /** Try Moodle's own AI subsystem before the bridge's providers. */
    const ROUTE_FIRST = 'first';

    /** Try Moodle's own AI subsystem after the bridge's providers. */
    const ROUTE_LAST = 'last';

    /** Never use Moodle's own AI subsystem. */
    const ROUTE_NEVER = 'never';

    /**
     * Does this site have Moodle's own AI subsystem?
     *
     * @return bool
     */
    public static function is_installed(): bool {
        return class_exists('\core_ai\manager');
    }

    /**
     * Get the AI manager.
     *
     * @return object a \core_ai\manager.
     */
    protected static function get_manager() {
        if (class_exists('\core\di')) {
            return \core\di::get('core_ai\manager');
        }
        return new \core_ai\manager();
    }

    /**
     * Can Moodle's own AI subsystem do an action?
     *
     * @param string|null $class the core action class, e.g. \core_ai\aiactions\generate_text.
     * @return bool
     */
    public static function is_action_available(?string $class): bool {
        if ($class === null || !self::is_installed() || !class_exists($class)) {
            return false;
        }
        try {
            return (bool) self::get_manager()->is_action_available(ltrim($class, '\\'));
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Send an action to Moodle's own AI subsystem. Moodle logs it in its own AI usage report.
     *
     * @param object $coreaction a \core_ai\aiactions\base.
     * @return object a \core_ai\aiactions\responses\response_base.
     */
    public static function process($coreaction) {
        return self::get_manager()->process_action($coreaction);
    }

    /**
     * Get the names of Moodle's enabled AI providers, for the data notice.
     *
     * @return string[]
     */
    public static function get_provider_names(): array {
        $names = [];
        if (!self::is_installed()) {
            return $names;
        }
        try {
            $manager = self::get_manager();
            if (method_exists($manager, 'get_provider_instances')) {
                foreach ($manager->get_provider_instances(['enabled' => 1]) as $provider) {
                    $names[] = $provider->name;
                }
            } else {
                foreach (\core\plugininfo\aiprovider::get_enabled_plugins() as $plugin) {
                    $names[] = get_string('pluginname', 'aiprovider_' . $plugin);
                }
            }
        } catch (\Throwable $e) {
            return [];
        }
        return $names;
    }
}
