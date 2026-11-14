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

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use tool_aibridge\privacy\provider as privacy_provider;

/**
 * Tests for the privacy provider.
 *
 * @package    tool_aibridge
 * @category   test
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_aibridge\privacy\provider::class)]
final class privacy_test extends \core_privacy\tests\provider_testcase {
    /** @var \stdClass a teacher, who adds a provider and sends requests. */
    protected $teacher;

    /** @var \stdClass a student, who sends requests. */
    protected $student;

    /** @var \context_course */
    protected $context;

    /**
     * Add a provider, accept the policy, and send a request as each user.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('coreroute', core_bridge::ROUTE_NEVER, 'tool_aibridge');
        set_config('logprompts', 1, 'tool_aibridge');
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $this->context = \context_course::instance($course->id);
        $this->teacher = $gen->create_and_enrol($course, 'editingteacher');
        $this->student = $gen->create_and_enrol($course, 'student');
        $gen->get_plugin_generator('tool_aibridge')->create_provider([
            'name' => 'Teacher GPT', 'ownerid' => $this->teacher->id, 'contextid' => $this->context->id,
            'apikey' => 'sk-teacher-secret',
        ]);
        foreach ([$this->teacher, $this->student] as $user) {
            policy::accept($user->id, $this->context->id);
            \curl::mock_response('{"choices": [{"message": {"content": "Hi"}}]}');
            $action = new action\generate_text($this->context->id, $user->id, 'Prompt from ' . $user->id);
            $this->assertTrue(manager::process($action)->get_success());
        }
    }

    /**
     * The contexts and users with data are found.
     */
    public function test_get_contexts_and_users(): void {
        $contextids = privacy_provider::get_contexts_for_userid($this->teacher->id)->get_contextids();
        $this->assertContainsEquals($this->context->id, $contextids);
        $this->assertContainsEquals(\context_user::instance($this->teacher->id)->id, $contextids);

        $userlist = new userlist($this->context, 'tool_aibridge');
        privacy_provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$this->teacher->id, $this->student->id], $userlist->get_userids());
    }

    /**
     * A user's data is exported, without the API key.
     */
    public function test_export(): void {
        $contextlist = new approved_contextlist(
            $this->teacher,
            'tool_aibridge',
            [$this->context->id, \context_user::instance($this->teacher->id)->id]
        );
        privacy_provider::export_user_data($contextlist);
        $writer = writer::with_context($this->context);
        $plugin = get_string('pluginname', 'tool_aibridge');
        $log = $writer->get_data([$plugin, get_string('log', 'tool_aibridge')]);
        $this->assertCount(1, $log->requests);
        $this->assertSame('Prompt from ' . $this->teacher->id, $log->requests[0]->prompt);
        $providers = $writer->get_data([$plugin, get_string('providers', 'tool_aibridge')]);
        $this->assertSame('Teacher GPT', $providers->providers[0]->name);
        $this->assertStringNotContainsString('sk-teacher-secret', json_encode($providers));
        $this->assertFalse(property_exists($providers->providers[0], 'apikey'));

        $policy = writer::with_context(\context_user::instance($this->teacher->id))
                ->get_data([$plugin, get_string('policy', 'tool_aibridge')]);
        $this->assertNotEmpty($policy->timeaccepted);
    }

    /**
     * A user's data is deleted.
     */
    public function test_delete_for_user(): void {
        global $DB;
        $contextlist = new approved_contextlist(
            $this->teacher,
            'tool_aibridge',
            [$this->context->id, \context_user::instance($this->teacher->id)->id]
        );
        privacy_provider::delete_data_for_user($contextlist);
        $this->assertFalse($DB->record_exists(logger::TABLE, ['userid' => $this->teacher->id]));
        $this->assertFalse($DB->record_exists(provider::TABLE, ['ownerid' => $this->teacher->id]));
        $this->assertFalse($DB->record_exists(policy::TABLE, ['userid' => $this->teacher->id]));
        $this->assertTrue($DB->record_exists(logger::TABLE, ['userid' => $this->student->id]));
        $this->assertTrue($DB->record_exists(policy::TABLE, ['userid' => $this->student->id]));
    }

    /**
     * Some users' data is deleted in a context.
     */
    public function test_delete_for_users(): void {
        global $DB;
        $userlist = new approved_userlist($this->context, 'tool_aibridge', [$this->student->id]);
        privacy_provider::delete_data_for_users($userlist);
        $this->assertFalse($DB->record_exists(logger::TABLE, ['userid' => $this->student->id]));
        $this->assertTrue($DB->record_exists(logger::TABLE, ['userid' => $this->teacher->id]));
    }

    /**
     * Everyone's data is deleted in a context.
     */
    public function test_delete_all(): void {
        global $DB;
        privacy_provider::delete_data_for_all_users_in_context($this->context);
        $this->assertSame(0, $DB->count_records(logger::TABLE));
        $this->assertSame(0, $DB->count_records(provider::TABLE));
    }
}
