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

#[\PHPUnit\Framework\Attributes\CoversClass(\tool_aibridge\tuning::class)]
/**
 * Tests for fine-tuning jobs. Requests get mocked replies.
 *
 * @package    tool_aibridge
 * @category   test
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_aibridge\tuning
 */
final class tuning_test extends \advanced_testcase {
    /**
     * Make a training file of examples.
     *
     * @return string
     */
    protected static function get_training(): string {
        $lines = [];
        for ($i = 1; $i <= 10; $i++) {
            $lines[] = json_encode(['messages' => [
                ['role' => 'user', 'content' => "Question $i"],
                ['role' => 'assistant', 'content' => "Answer $i"],
            ]]);
        }
        return implode("\n", $lines);
    }

    /**
     * Which providers can be tuned, and which model is tuned.
     */
    public function test_is_tunable(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_aibridge');
        $openai = $generator->create_provider(['preset' => 'openai']);
        $claude = $generator->create_provider(['preset' => 'claude']);
        $speech = $generator->create_provider(['preset' => 'openaitts']);
        $this->assertTrue(tuning::is_tunable($openai));
        $this->assertFalse(tuning::is_tunable($claude));
        $this->assertFalse(tuning::is_tunable($speech));
        $this->assertSame('https://api.openai.com/v1', tuning::get_base_url($openai));

        $this->assertSame('gpt-4o-mini-2024-07-18', tuning::get_tunable_model('gpt-4o-mini'));
        $this->assertSame('gpt-4.1-mini-2025-04-14', tuning::get_tunable_model('gpt-4.1-mini'));
        $this->assertSame('gpt-4o-2024-08-06', tuning::get_tunable_model('gpt-4o-2024-08-06'));
        $this->assertSame('my-model', tuning::get_tunable_model('my-model'));

        $this->expectException(\moodle_exception::class);
        tuning::create($claude, 'Claude', SYSCONTEXTID, 2, 'a.jsonl', self::get_training());
    }

    /**
     * A job uploads its file, starts, runs, and adds a provider with the tuned model.
     */
    public function test_advance(): void {
        global $DB;
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_aibridge');
        $provider = $generator->create_provider(['preset' => 'openai', 'name' => 'ChatGPT', 'model' => 'gpt-4o-mini',
            'ownerid' => $teacher->id, 'apikey' => 'sk-secret']);

        $tuning = tuning::create($provider, 'TOEIC Part 2', $context->id, $teacher->id, 'toeic.jsonl', self::get_training());
        $this->assertSame(tuning::STATUS_WAITING, $tuning->get_record()->status);
        $this->assertSame('gpt-4o-mini-2024-07-18', $tuning->get_record()->basemodel);
        $this->assertSame('toeic.jsonl', $tuning->get_file()->get_filename());

        \curl::mock_response(json_encode(['id' => 'file-123', 'purpose' => 'fine-tune']));
        $this->assertSame(tuning::STATUS_UPLOADED, $tuning->advance());
        $this->assertSame('file-123', $tuning->get_record()->remotefileid);

        \curl::mock_response(json_encode(['id' => 'ftjob-456', 'status' => 'validating_files']));
        $this->assertSame(tuning::STATUS_RUNNING, $tuning->advance());

        \curl::mock_response(json_encode(['id' => 'ftjob-456', 'status' => 'running']));
        $this->assertSame(tuning::STATUS_RUNNING, $tuning->advance());

        // The scheduled task does the same.
        \curl::mock_response(json_encode(['id' => 'ftjob-456', 'status' => 'succeeded',
            'fine_tuned_model' => 'ft:gpt-4o-mini-2024-07-18:org:toeic:abc']));
        $this->assertSame(1, tuning::advance_all());
        $tuning = tuning::get($tuning->get_id());
        $this->assertSame(tuning::STATUS_SUCCEEDED, $tuning->get_record()->status);

        // The tuned provider is like the first, with the tuned model, shared in the course.
        $tuned = provider::get((int) $tuning->get_record()->tunedproviderid);
        $this->assertSame('ChatGPT (TOEIC Part 2)', $tuned->get_name());
        $this->assertSame('ft:gpt-4o-mini-2024-07-18:org:toeic:abc', $tuned->get_record()->model);
        $this->assertEquals($context->id, $tuned->get_record()->contextid);
        $this->assertEquals($teacher->id, $tuned->get_record()->ownerid);
        $this->assertSame('sk-secret', $tuned->get_apikey());

        // Finished jobs are not checked again.
        $this->assertSame(0, tuning::advance_all());

        // Deleting the job deletes its file, but keeps the tuned provider.
        $tuning->delete();
        $this->assertFalse($DB->record_exists(tuning::TABLE, ['id' => $tuning->get_id()]));
        $this->assertNull($tuning->get_file());
        $this->assertTrue($DB->record_exists(provider::TABLE, ['id' => $tuned->get_id()]));
    }

    /**
     * Failures: a job that fails at the provider ends; a busy provider is tried again later.
     */
    public function test_failures(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_aibridge');
        $provider = $generator->create_provider(['preset' => 'openai']);

        $tuning = tuning::create($provider, 'Job', SYSCONTEXTID, 2, 'a.jsonl', self::get_training(), 'gpt-4.1-mini-2025-04-14');
        $this->assertSame('gpt-4.1-mini-2025-04-14', $tuning->get_record()->basemodel);
        \curl::mock_response(json_encode(['id' => 'file-1']));
        $tuning->advance();
        \curl::mock_response(json_encode(['id' => 'ftjob-1', 'status' => 'queued']));
        $tuning->advance();
        \curl::mock_response(json_encode(['id' => 'ftjob-1', 'status' => 'failed', 'error' => ['message' => 'Invalid file']]));
        $this->assertSame(tuning::STATUS_FAILED, $tuning->advance());
        $this->assertStringContainsString('Invalid file', $tuning->get_record()->error);

        // The provider cannot be reached: the job waits, with the error.
        $unreachable = $generator->create_provider(['preset' => 'openai', 'url' => 'http://127.0.0.1:1/v1/chat/completions']);
        set_config('curlsecurityblockedhosts', '');
        set_config('curlsecurityallowedport', '');
        $tuning = tuning::create($unreachable, 'Later', SYSCONTEXTID, 2, 'a.jsonl', self::get_training());
        $this->assertSame(tuning::STATUS_WAITING, $tuning->advance());
        $this->assertNotEmpty($tuning->get_record()->error);

        // The provider was deleted.
        $unreachable->delete();
        $this->assertSame(tuning::STATUS_FAILED, $tuning->advance());
    }

    /**
     * Tunings are personal data of their owners: they are exported and deleted.
     */
    public function test_privacy(): void {
        global $DB;
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();
        $context = \context_user::instance($teacher->id);
        $provider = $this->getDataGenerator()->get_plugin_generator('tool_aibridge')->create_provider(['preset' => 'openai']);
        $tuning = tuning::create($provider, 'Mine', $context->id, $teacher->id, 'a.jsonl', self::get_training());

        $contextids = privacy\provider::get_contexts_for_userid($teacher->id)->get_contextids();
        $this->assertContains((string) $context->id, array_map('strval', $contextids));

        $contextlist = new \core_privacy\local\request\approved_contextlist($teacher, 'tool_aibridge', [$context->id]);
        privacy\provider::export_user_data($contextlist);
        $writer = \core_privacy\local\request\writer::with_context($context);
        $data = $writer->get_data([get_string('pluginname', 'tool_aibridge'), get_string('tunings', 'tool_aibridge')]);
        $this->assertSame('Mine', $data->tunings[0]->name);

        privacy\provider::delete_data_for_user($contextlist);
        $this->assertFalse($DB->record_exists(tuning::TABLE, ['id' => $tuning->get_id()]));
        $this->assertNull($tuning->get_file());
    }
}
