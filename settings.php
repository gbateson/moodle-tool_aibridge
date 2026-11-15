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
 * Site settings for the AI bridge.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$systemcontext = context_system::instance();
if (
    $hassiteconfig || has_capability('tool/aibridge:manageproviders', $systemcontext)
        || has_capability('tool/aibridge:managetemplates', $systemcontext)
) {
    // Moodle 4.5 and later have an "AI" category; earlier versions put the bridge under "Admin tools".
    $parent = $ADMIN->locate('ai') ? 'ai' : 'tools';
    $ADMIN->add($parent, new admin_category('tool_aibridge', new lang_string('pluginname', 'tool_aibridge')));

    $settings = new admin_settingpage('tool_aibridge_settings', new lang_string('settings', 'tool_aibridge'));
    if ($ADMIN->fulltree) {
        $plugin = 'tool_aibridge';

        $settings->add(new admin_setting_configcheckbox(
            "$plugin/enabled",
            new lang_string('enabled', $plugin),
            new lang_string('enabled_desc', $plugin),
            1
        ));

        $settings->add(new admin_setting_heading(
            "$plugin/routesheading",
            new lang_string('routes', $plugin),
            ''
        ));
        $settings->add(new admin_setting_configcheckbox(
            "$plugin/allowexternal",
            new lang_string('allowexternal', $plugin),
            new lang_string('allowexternal_desc', $plugin),
            1
        ));
        $settings->add(new admin_setting_configcheckbox(
            "$plugin/allowuserproviders",
            new lang_string('allowuserproviders', $plugin),
            new lang_string('allowuserproviders_desc', $plugin),
            1
        ));
        $options = [
            \tool_aibridge\core_bridge::ROUTE_FIRST => new lang_string('coreroutefirst', $plugin),
            \tool_aibridge\core_bridge::ROUTE_LAST => new lang_string('coreroutelast', $plugin),
            \tool_aibridge\core_bridge::ROUTE_NEVER => new lang_string('coreroutenever', $plugin),
        ];
        $settings->add(new admin_setting_configselect(
            "$plugin/coreroute",
            new lang_string('coreroute', $plugin),
            new lang_string('coreroute_desc', $plugin),
            \tool_aibridge\core_bridge::ROUTE_FIRST,
            $options
        ));

        $settings->add(new admin_setting_heading(
            "$plugin/policyheading",
            new lang_string('policy', $plugin),
            ''
        ));
        $settings->add(new admin_setting_configcheckbox(
            "$plugin/requirepolicy",
            new lang_string('requirepolicy', $plugin),
            new lang_string('requirepolicy_desc', $plugin),
            1
        ));
        $settings->add(new admin_setting_confightmleditor(
            "$plugin/policytext",
            new lang_string('policytext', $plugin),
            new lang_string('policytext_desc', $plugin),
            ''
        ));

        $settings->add(new admin_setting_heading(
            "$plugin/limitsheading",
            new lang_string('ratelimits', $plugin),
            new lang_string('ratelimits_desc', $plugin)
        ));
        $settings->add(new admin_setting_configtext(
            "$plugin/siteratelimit",
            new lang_string('siteratelimit', $plugin),
            new lang_string('siteratelimit_desc', $plugin),
            0,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            "$plugin/userratelimit",
            new lang_string('userratelimit', $plugin),
            new lang_string('siteuserratelimit_desc', $plugin),
            0,
            PARAM_INT
        ));

        // Media files: how the images and audio that AI providers make are converted.
        $status = '';
        if (!during_initial_install() && !empty($CFG->version)) {
            $status = \tool_aibridge\media_converter::get_status_html();
        }
        $settings->add(new admin_setting_heading(
            "$plugin/mediaheading",
            new lang_string('mediafiles', $plugin),
            new lang_string('mediafiles_desc', $plugin) . $status
        ));
        $settings->add(new admin_setting_configexecutable(
            "$plugin/pathtoffmpeg",
            new lang_string('pathtoffmpeg', $plugin),
            new lang_string('pathtoffmpeg_desc', $plugin),
            \tool_aibridge\media_converter::DEFAULT_FFMPEG
        ));
        $settings->add(new admin_setting_configselect(
            "$plugin/imageformat",
            new lang_string('imageformat', $plugin),
            new lang_string('imageformat_desc', $plugin),
            \tool_aibridge\media_converter::DEFAULT_IMAGE_FORMAT,
            [
                'jpg' => 'JPEG',
                'png' => 'PNG',
                \tool_aibridge\media_converter::FORMAT_KEEP => new lang_string('formatkeep', $plugin),
            ]
        ));
        $sides = [0 => new lang_string('anysize', $plugin)];
        foreach ([512, 768, 1024, 1280, 1536, 2048] as $side) {
            $sides[$side] = new lang_string('pixels', $plugin, $side);
        }
        $settings->add(new admin_setting_configselect(
            "$plugin/imagemaxside",
            new lang_string('imagemaxside', $plugin),
            new lang_string('imagemaxside_desc', $plugin),
            \tool_aibridge\media_converter::DEFAULT_IMAGE_MAX_SIDE,
            $sides
        ));
        $qualities = [];
        foreach ([60, 70, 75, 80, 85, 90, 95] as $quality) {
            $qualities[$quality] = $quality;
        }
        $settings->add(new admin_setting_configselect(
            "$plugin/imagequality",
            new lang_string('imagequality', $plugin),
            new lang_string('imagequality_desc', $plugin),
            \tool_aibridge\media_converter::DEFAULT_IMAGE_QUALITY,
            $qualities
        ));
        $settings->add(new admin_setting_configselect(
            "$plugin/audioformat",
            new lang_string('audioformat', $plugin),
            new lang_string('audioformat_desc', $plugin),
            \tool_aibridge\media_converter::DEFAULT_AUDIO_FORMAT,
            [
                'mp3' => 'MP3',
                \tool_aibridge\media_converter::FORMAT_KEEP => new lang_string('formatkeep', $plugin),
            ]
        ));
        $bitrates = [];
        foreach (\tool_aibridge\media_converter::AUDIO_BITRATES as $bitrate) {
            $bitrates[$bitrate] = new lang_string('bitrate', $plugin, (int) $bitrate);
        }
        $settings->add(new admin_setting_configselect(
            "$plugin/audiobitrate",
            new lang_string('audiobitrate', $plugin),
            new lang_string('audiobitrate_desc', $plugin),
            \tool_aibridge\media_converter::DEFAULT_AUDIO_BITRATE,
            $bitrates
        ));

        $settings->add(new admin_setting_heading(
            "$plugin/logheading",
            new lang_string('log', $plugin),
            ''
        ));
        $settings->add(new admin_setting_configcheckbox(
            "$plugin/logprompts",
            new lang_string('logprompts', $plugin),
            new lang_string('logprompts_desc', $plugin),
            0
        ));
        $settings->add(new admin_setting_configcheckbox(
            "$plugin/logresponses",
            new lang_string('logresponses', $plugin),
            new lang_string('logresponses_desc', $plugin),
            0
        ));
        $days = [0 => new lang_string('never')];
        foreach ([7, 30, 90, 180, 365, 730] as $day) {
            $days[$day] = new lang_string('numdays', '', $day);
        }
        $settings->add(new admin_setting_configselect(
            "$plugin/contentretention",
            new lang_string('contentretention', $plugin),
            new lang_string('contentretention_desc', $plugin),
            30,
            $days
        ));
        $settings->add(new admin_setting_configselect(
            "$plugin/logretention",
            new lang_string('logretention', $plugin),
            new lang_string('logretention_desc', $plugin),
            365,
            $days
        ));
    }
    $ADMIN->add('tool_aibridge', $settings);

    $ADMIN->add('tool_aibridge', new admin_externalpage(
        'tool_aibridge_providers',
        new lang_string('providers', 'tool_aibridge'),
        new moodle_url('/admin/tool/aibridge/index.php'),
        'tool/aibridge:manageproviders'
    ));
    $ADMIN->add('tool_aibridge', new admin_externalpage(
        'tool_aibridge_tunings',
        new lang_string('tunings', 'tool_aibridge'),
        new moodle_url('/admin/tool/aibridge/tunings.php'),
        'tool/aibridge:manageproviders'
    ));
    $ADMIN->add('tool_aibridge', new admin_externalpage(
        'tool_aibridge_library',
        new lang_string('library', 'tool_aibridge'),
        new moodle_url('/admin/tool/aibridge/library.php'),
        'tool/aibridge:managetemplates'
    ));
    $ADMIN->add('tool_aibridge', new admin_externalpage(
        'tool_aibridge_log',
        new lang_string('log', 'tool_aibridge'),
        new moodle_url('/admin/tool/aibridge/log.php'),
        'tool/aibridge:viewlog'
    ));
}
