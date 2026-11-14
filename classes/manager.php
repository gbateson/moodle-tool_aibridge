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

use tool_aibridge\action\base;

/**
 * Send AI actions to Moodle's own AI subsystem or to the bridge's providers.
 *
 * Example:
 *
 *     $action = new \tool_aibridge\action\generate_text($context->id, $USER->id, $prompt);
 *     $action->set_purpose('mod_vocab', 'questions');
 *     $response = \tool_aibridge\manager::process($action);
 *     if ($response->get_success()) {
 *         $text = $response->get_content();
 *     } else if ($response->get_errorcode() == 'policynotaccepted') {
 *         redirect(\tool_aibridge\policy::get_url($context, $PAGE->url));
 *     }
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** The route to Moodle's own AI subsystem. */
    const CORE = 'core';

    /**
     * Is the bridge enabled?
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        $enabled = get_config('tool_aibridge', 'enabled');
        return ($enabled === false || !empty($enabled));
    }

    /**
     * May requests be sent to the bridge's own providers, rather than only to Moodle's AI subsystem?
     *
     * @return bool
     */
    public static function is_external_allowed(): bool {
        $allowed = get_config('tool_aibridge', 'allowexternal');
        return ($allowed === false || !empty($allowed));
    }

    /**
     * Get the routes that an action could take, in the order they are tried.
     *
     * @param base $action
     * @param int $providerid one provider to use, or 0 for any.
     * @return array of manager::CORE, or provider objects.
     */
    public static function get_routes(base $action, int $providerid = 0): array {
        if (!self::is_enabled()) {
            return [];
        }
        $context = $action->get_context();
        $userid = $action->get_userid();
        if ($providerid) {
            if (!self::is_external_allowed()) {
                return [];
            }
            $providers = provider::get_available($context, $action->get_capabilities(), $userid);
            return isset($providers[$providerid]) ? [$providers[$providerid]] : [];
        }

        $routes = [];
        if (self::is_external_allowed()) {
            $routes = array_values(provider::get_available($context, $action->get_capabilities(), $userid));
        }
        $coreroute = get_config('tool_aibridge', 'coreroute');
        if ($coreroute !== core_bridge::ROUTE_NEVER && core_bridge::is_action_available($action->get_core_class())) {
            if ($coreroute === core_bridge::ROUTE_LAST) {
                $routes[] = self::CORE;
            } else {
                array_unshift($routes, self::CORE);
            }
        }
        return $routes;
    }

    /**
     * Can an action be done? This does not check the policy or rate limits.
     *
     * @param base $action
     * @return bool
     */
    public static function is_available(base $action): bool {
        return (count(self::get_routes($action)) > 0);
    }

    /**
     * Do an action. Each route is tried in turn until one succeeds.
     *
     * @param base $action
     * @param int $providerid one provider to use, or 0 for any.
     * @return response
     */
    public static function process(base $action, int $providerid = 0): response {
        if (!self::is_enabled()) {
            return response::failure('disabled');
        }
        try {
            $action->validate();
        } catch (\moodle_exception $e) {
            return response::from_exception($e, $e->getMessage());
        }
        if (!policy::has_accepted($action->get_userid())) {
            return response::failure('policynotaccepted');
        }
        if ($errorcode = rate_limiter::check_site($action->get_userid())) {
            return response::failure($errorcode);
        }
        $routes = self::get_routes($action, $providerid);
        if (empty($routes)) {
            return response::failure('noproviders');
        }
        $response = null;
        foreach ($routes as $route) {
            if ($route === self::CORE) {
                $response = self::process_core($action);
            } else if (rate_limiter::check_provider($route, $action->get_userid())) {
                $response = self::process_external($action, $route);
            } else if ($response === null) {
                $response = response::failure(
                    'providerratelimit',
                    '',
                    response::ROUTE_EXTERNAL,
                    $route->get_id(),
                    $route->get_name()
                );
                continue;
            } else {
                continue;
            }
            if ($response->get_success()) {
                break;
            }
        }
        return $response;
    }

    /**
     * Test a provider: send it a short request, without checking the policy or rate limits.
     *
     * @param provider $provider
     * @param \context $context
     * @param int $userid
     * @return response
     */
    public static function test(provider $provider, \context $context, int $userid): response {
        if ($provider->get_capability() === provider::CAPABILITY_IMAGE) {
            $action = new action\generate_image($context->id, $userid, get_string('testimage', 'tool_aibridge'));
        } else if ($provider->get_capability() === provider::CAPABILITY_SPEECH) {
            $action = new action\generate_speech($context->id, $userid, get_string('testspeech', 'tool_aibridge'));
        } else {
            $action = new action\generate_text($context->id, $userid, get_string('testprompt', 'tool_aibridge'));
        }
        $action->set_purpose('tool_aibridge', 'test');
        try {
            $action->validate();
        } catch (\moodle_exception $e) {
            return response::from_exception($e, $e->getMessage());
        }
        return self::process_external($action, $provider);
    }

    /**
     * Send an action to one of the bridge's providers, and log it.
     *
     * @param base $action
     * @param provider $provider
     * @return response
     */
    protected static function process_external(base $action, provider $provider): response {
        $timecreated = time();
        try {
            $info = [];
            $reply = caller::send($provider, $action->get_tokens($provider), $action->get_files(), $info);
            $response = $action->parse_reply($reply, $info, $provider);
        } catch (\moodle_exception $e) {
            $error = caller::hide_key($e->getMessage(), $provider->get_apikey());
            $response = response::from_exception(
                $e,
                $error,
                response::ROUTE_EXTERNAL,
                $provider->get_id(),
                $provider->get_name()
            );
            $response->set_model((string) $provider->get_record()->model);
        }
        logger::log($action, $response, $timecreated);
        return $response;
    }

    /**
     * Send an action to Moodle's own AI subsystem, and log it.
     * Moodle also logs it in its own AI usage report.
     *
     * @param base $action
     * @return response
     */
    protected static function process_core(base $action): response {
        $timecreated = time();
        try {
            $response = $action->parse_core_response(core_bridge::process($action->create_core_action()));
        } catch (\Throwable $e) {
            $response = response::failure(
                'core',
                $e->getMessage(),
                response::ROUTE_CORE,
                0,
                get_string('coreai', 'tool_aibridge')
            );
        }
        logger::log($action, $response, $timecreated);
        return $response;
    }
}
