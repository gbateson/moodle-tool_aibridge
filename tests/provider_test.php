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

#[\PHPUnit\Framework\Attributes\CoversClass(\tool_aibridge\provider::class)]
/**
 * Tests for providers: keys, sharing and permissions.
 *
 * @package    tool_aibridge
 * @category   test
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_aibridge\provider
 */
final class provider_test extends \advanced_testcase {
    /**
     * Get the generator.
     *
     * @return \tool_aibridge_generator
     */
    protected function get_generator(): \tool_aibridge_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_aibridge');
    }

    /**
     * API keys are encrypted, can be kept or removed, and are never in the record as plain text.
     */
    public function test_apikey(): void {
        global $DB;
        $this->resetAfterTest();
        $provider = $this->get_generator()->create_provider(['apikey' => 'sk-secret']);
        $stored = $DB->get_field(provider::TABLE, 'apikey', ['id' => $provider->get_id()]);
        $this->assertNotEmpty($stored);
        $this->assertStringNotContainsString('sk-secret', $stored);
        $this->assertSame('sk-secret', $provider->get_apikey());

        // Saving without a key keeps the key.
        $provider = provider::save((object) ['id' => $provider->get_id(), 'name' => 'Renamed']);
        $this->assertSame('sk-secret', $provider->get_apikey());
        $this->assertSame('Renamed', $provider->get_name());

        // An empty key removes the key.
        $provider = provider::save((object) ['id' => $provider->get_id()], '');
        $this->assertFalse($provider->has_apikey());
        $this->assertSame('', $provider->get_apikey());
    }

    /**
     * Providers are available where they are shared, to users who may use them.
     */
    public function test_get_available(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $category = $gen->create_category();
        $course1 = $gen->create_course(['category' => $category->id]);
        $course2 = $gen->create_course();
        $teacher = $gen->create_and_enrol($course1, 'editingteacher');
        $student = $gen->create_and_enrol($course1, 'student');
        $gen->enrol_user($student->id, $course2->id, 'student');
        $context1 = \context_course::instance($course1->id);
        $context2 = \context_course::instance($course2->id);

        $site = $this->get_generator()->create_provider(['name' => 'Site']);
        $shared = $this->get_generator()->create_provider(['name' => 'Course 1', 'ownerid' => $teacher->id,
                'contextid' => $context1->id]);
        $personal = $this->get_generator()->create_provider(['name' => 'Personal', 'ownerid' => $teacher->id,
                'contextid' => \context_user::instance($teacher->id)->id]);
        $cat = $this->get_generator()->create_provider(['name' => 'Category', 'ownerid' => $teacher->id,
                'contextid' => \context_coursecat::instance($category->id)->id, 'sortorder' => 1]);
        $speech = $this->get_generator()->create_provider(['name' => 'Speech', 'capability' => 'speech']);
        $this->get_generator()->create_provider(['name' => 'Off', 'enabled' => 0]);
        $this->get_generator()->create_provider(['name' => 'Later', 'sharedfrom' => time() + DAYSECS]);
        $this->get_generator()->create_provider(['name' => 'Over', 'shareduntil' => time() - DAYSECS]);

        $text = [provider::CAPABILITY_TEXT];

        // The student sees the course, category and site providers in course 1, nearest first.
        $names = $this->get_names(provider::get_available($context1, $text, $student->id));
        $this->assertSame(['Course 1', 'Category', 'Site'], $names);

        // In course 2, only the site's provider.
        $this->assertSame(['Site'], $this->get_names(provider::get_available($context2, $text, $student->id)));

        // The teacher's own providers come first, and can be used anywhere.
        $names = $this->get_names(provider::get_available($context2, $text, $teacher->id));
        $this->assertSame(['Course 1', 'Personal', 'Category', 'Site'], $names);

        // Speech providers are separate.
        $names = $this->get_names(provider::get_available($context1, [provider::CAPABILITY_SPEECH], $student->id));
        $this->assertSame(['Speech'], $names);

        // Shared providers are those that anyone there can use: not personal ones, nearest first.
        $names = $this->get_names(provider::get_shared($context1, $text));
        $this->assertSame(['Course 1', 'Category', 'Site'], $names);
        $this->assertSame(['Site'], $this->get_names(provider::get_shared($context2, $text)));

        // Without the capability to use providers, only one's own providers.
        $studentrole = $this->getDataGenerator()->create_role();
        assign_capability('tool/aibridge:useprovider', CAP_PROHIBIT, $studentrole, $context1->id);
        role_assign($studentrole, $student->id, $context1->id);
        $this->assertSame([], $this->get_names(provider::get_available($context1, $text, $student->id)));

        // If teachers cannot add providers, only the site's providers are shared.
        set_config('allowuserproviders', 0, 'tool_aibridge');
        $this->assertSame(['Site'], $this->get_names(provider::get_available($context2, $text, $student->id)));
        $this->assertTrue($personal->can_use($context2, $teacher->id));
        $this->assertFalse($shared->can_use($context1, $student->id));
        $this->assertFalse($cat->can_use($context1, $student->id));
        $this->assertTrue($site->can_use($context2, $student->id));
        $this->assertTrue($speech->can_use($context2, $student->id));
    }

    /**
     * Only owners and managers can edit providers.
     */
    public function test_can_edit(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $other = $gen->create_and_enrol($course, 'editingteacher');
        $provider = $this->get_generator()->create_provider(['ownerid' => $teacher->id,
                'contextid' => \context_course::instance($course->id)->id]);
        $this->assertTrue($provider->can_edit($teacher->id));
        $this->assertFalse($provider->can_edit($other->id));
        $this->assertTrue($provider->can_edit(get_admin()->id));
    }

    /**
     * Teachers can share in their courses and categories, and managers with the whole site.
     */
    public function test_get_sharing_options(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $context = \context_course::instance($course->id);
        $options = provider::get_sharing_options($context, $teacher->id);
        $this->assertSame([\context_user::instance($teacher->id)->id, $context->id], array_keys($options));

        $options = provider::get_sharing_options($context, get_admin()->id);
        $this->assertArrayHasKey(SYSCONTEXTID, $options);
    }

    /**
     * The data notice names the host that data goes to.
     */
    public function test_data_notice(): void {
        $this->resetAfterTest();
        $provider = $this->get_generator()->create_provider(['name' => 'GPT']);
        $this->assertSame('api.openai.com', $provider->get_host());
        $this->assertStringContainsString('api.openai.com', $provider->get_data_notice());
        $provider = provider::save((object) ['id' => $provider->get_id(), 'datanotice' => 'Custom notice']);
        $this->assertSame('Custom notice', $provider->get_data_notice());
    }

    /**
     * Get the names of some providers.
     *
     * @param provider[] $providers
     * @return string[]
     */
    protected function get_names(array $providers): array {
        $names = [];
        foreach ($providers as $provider) {
            $names[] = $provider->get_name();
        }
        return $names;
    }
}
