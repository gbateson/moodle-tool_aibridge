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

use tool_aibridge\library\template;

/**
 * Tests for the AI prompt library: shipped templates, rendering, versions, sharing, import and export.
 *
 * @package    tool_aibridge
 * @category   test
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_aibridge\library::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_aibridge\library\template::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_aibridge\library\renderer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_aibridge\library\rendering::class)]
final class library_test extends \advanced_testcase {
    /** @var \context_system */
    protected $context;

    /** @var int the admin's id. */
    protected $adminid;

    /**
     * Load the bridge's own templates (installed with the plugin) and the TOEIC test templates.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $this->context = \context_system::instance();
        $this->adminid = (int) get_admin()->id;
        $templates = [];
        include($CFG->dirroot . '/admin/tool/aibridge/tests/fixtures/toeic_templates.php');
        library::sync_definitions('mod_vocab', $templates);
    }

    /**
     * Render a prompt as the admin.
     *
     * @param string $shortname
     * @param array $values
     * @param string $qtype
     * @return library\rendering
     */
    protected function render(string $shortname, array $values = [], string $qtype = 'multichoice'): library\rendering {
        return library::render($shortname, $this->context, $this->adminid, $values, $qtype);
    }

    /**
     * The bridge's own templates are installed, and a second sync changes nothing.
     */
    public function test_shipped_templates(): void {
        global $DB;
        $prompt = template::find('tool_aibridge:generate-questions');
        $this->assertNotNull($prompt);
        $this->assertTrue($prompt->is_shipped());
        $this->assertFalse($prompt->can_edit($this->adminid));
        $this->assertSame(1, $prompt->get_version());
        $count = $DB->count_records(template::VERSIONS_TABLE);

        library::sync_all();
        $this->assertSame($count, $DB->count_records(template::VERSIONS_TABLE));
        $this->assertSame(1, template::find('tool_aibridge:generate-questions')->get_version());
        // A prompt that extends another starts at version 1 too.
        $this->assertSame(1, template::find('mod_vocab:toeic-part2')->get_version());
    }

    /**
     * A changed definition makes a new version, and a removed one is disabled.
     */
    public function test_sync_changes(): void {
        $definitions = ['one' => ['type' => 'fragment', 'name' => 'One', 'body' => 'First'],
            'two' => ['type' => 'fragment', 'name' => 'Two', 'body' => 'Second']];
        library::sync_definitions('mod_test', $definitions);
        $definitions['one']['body'] = 'Changed';
        unset($definitions['two']);
        library::sync_definitions('mod_test', $definitions);
        $one = template::find('mod_test:one');
        $this->assertSame(2, $one->get_version());
        $this->assertSame('Changed', $one->get_body());
        $this->assertCount(2, $one->get_versions());
        $this->assertEquals(0, template::find('mod_test:two')->get_record()->enabled);
    }

    /**
     * The base prompt is rendered with its defaults, the GIFT format and the output rules.
     */
    public function test_render_base(): void {
        $rendering = $this->render('tool_aibridge:generate-questions', ['vocabulary-item' => 'reschedule']);
        $this->assertSame([], $rendering->get_errors());
        $this->assertSame([], $rendering->get_warnings());
        $text = $rendering->get_text();
        $this->assertStringContainsString('I teach English to Japanese students at university.', $text);
        $this->assertStringContainsString('Create 4 multiple-choice questions', $text);
        $this->assertStringContainsString('"reschedule"', $text);
        $this->assertStringContainsString("Moodle's GIFT format", $text);
        $this->assertStringContainsString('~wrong-answer-3 #Explanation', $text);
        $this->assertStringNotContainsString('{{', $text);
        $this->assertSame('gift', $rendering->get_qformat());
        $this->assertSame(
            ['tool_aibridge:generate-questions', 'tool_aibridge:gift-multichoice', 'tool_aibridge:output-rules'],
            array_keys($rendering->get_versions())
        );

        // The question type chooses the format.
        $text = $this->render('tool_aibridge:generate-questions', ['vocabulary-item' => 'x'], 'truefalse')->get_text();
        $this->assertStringContainsString('true/false questions', $text);
        $this->assertStringContainsString('statement-that-is-true', $text);

        // Values replace the defaults.
        $text = $this->render('tool_aibridge:generate-questions', ['vocabulary-item' => 'x', 'student-nationality' => 'Korean',
            'number-of-questions' => '10', 'level-of-questions' => 'A2'])->get_text();
        $this->assertStringContainsString('to Korean students', $text);
        $this->assertStringContainsString('Create 10 multiple-choice', $text);
        $this->assertStringContainsString('CEFR level A2 English', $text);
    }

    /**
     * A required parameter without a value is an error.
     */
    public function test_render_required(): void {
        $rendering = $this->render('tool_aibridge:generate-questions');
        $this->assertFalse($rendering->is_valid());
        $this->assertStringContainsString('vocabulary-item', implode(' ', $rendering->get_errors()));
    }

    /**
     * A prompt that extends another replaces its blocks, and its format and the fragments are included.
     */
    public function test_render_extends(): void {
        $rendering = $this->render('mod_vocab:toeic-part3', ['vocabulary-item' => 'warranty']);
        $this->assertSame([], $rendering->get_errors());
        $text = $rendering->get_text();
        // The parent's role block, the child's task and description blocks.
        $this->assertStringStartsWith('I am a language teacher.', $text);
        $this->assertStringContainsString('Create 4 multiple-choice question sets', $text);
        $this->assertStringContainsString('comprehend spoken conversations', $text);
        // The parent's rules, the child's format, and the format's fragment with its defaults.
        $this->assertStringContainsString('do not include any greetings', $text);
        $this->assertStringContainsString("Moodle's embedded answers (Cloze) format", $text);
        $this->assertStringContainsString('{1:MCVS:=correct-answer-A', $text);
        $this->assertStringContainsString('bilingual (English and Japanese)', $text);
        $this->assertStringContainsString('<span lang="ja" class="multilang">Japanese feedback</span>', $text);
        $this->assertStringContainsString('Why is the woman calling?', $text);
        $this->assertSame('multianswer', $rendering->get_qformat());
        $this->assertArrayHasKey('tool_aibridge:multilang-feedback', $rendering->get_versions());

        // Part 1 includes the image prompt rules.
        $text = $this->render('mod_vocab:toeic-part1', ['vocabulary-item' => 'printer'])->get_text();
        $this->assertStringContainsString('do not use any of the following words: boy, girl', $text);
        $this->assertStringContainsString('[[IMAGE image-prompt]]', $text);

        // The fixed samples.
        $text = $this->render('mod_vocab:toeic-part2', ['vocabulary-item' => 'report'])->get_text();
        $this->assertStringNotContainsString('wrong-answer-audio-3', $text);
        $this->assertStringContainsString('::Part-2.Q1::', $text);
        $this->assertStringContainsString('enclosed in double square brackets', $text);
        $text = $this->render('mod_vocab:toeic-part5', ['vocabulary-item' => 'report'])->get_text();
        $this->assertStringContainsString('a sample sentence', $text);
        $this->assertStringContainsString('sentence-with-blank', $text);
    }

    /**
     * Fragments, blocks and parameters made by users work in the same way, and problems are reported.
     */
    public function test_render_user_templates(): void {
        $fragment = template::save((object) ['type' => 'fragment', 'name' => 'Greeting', 'shortname' => 'greeting',
            'body' => 'Hello {{who}}!', 'parameters' => '{"who": "world"}', 'ownerid' => $this->adminid,
            'contextid' => SYSCONTEXTID], $this->adminid);
        $parent = template::save((object) ['type' => 'prompt', 'name' => 'Parent', 'shortname' => 'parent',
            'body' => "{{include:greeting}}\n{{block:main}}Default{{endblock}}\n{{unknown}}",
            'ownerid' => $this->adminid, 'contextid' => SYSCONTEXTID], $this->adminid);
        $child = template::save((object) ['type' => 'prompt', 'name' => 'Child', 'shortname' => 'child',
            'body' => '{{block:main}}Replaced{{endblock}}{{block:other}}X{{endblock}} stray text', 'parentid' => $parent->get_id(),
            'ownerid' => $this->adminid, 'contextid' => SYSCONTEXTID], $this->adminid);

        $rendering = $this->render('parent', ['who' => 'Moodle'], '');
        $this->assertSame("Hello Moodle!\nDefault\n{{unknown}}", $rendering->get_text());
        $this->assertCount(1, $rendering->get_warnings());

        $rendering = $this->render('child', [], '');
        $this->assertSame("Hello world!\nReplaced\n{{unknown}}", $rendering->get_text());
        $this->assertCount(3, $rendering->get_warnings());
        $this->assertSame(1, $fragment->get_version());

        // A loop of includes is an error.
        template::save((object) ['id' => $fragment->get_id(), 'body' => 'Loop {{include:greeting}}'], $this->adminid);
        $this->assertFalse($this->render('parent', [], '')->is_valid());

        // A missing format is an error.
        $rendering = $this->render('parent', [], '');
        $this->assertNotEmpty($rendering->get_errors());
    }

    /**
     * Each change makes a new version, and old versions can be restored.
     */
    public function test_versions(): void {
        $template = template::save((object) ['type' => 'fragment', 'name' => 'Rules', 'body' => 'Version one',
            'ownerid' => $this->adminid, 'contextid' => SYSCONTEXTID], $this->adminid);
        $this->assertSame('rules', $template->get_shortname());
        $template = template::save((object) ['id' => $template->get_id(), 'body' => 'Version two'], $this->adminid);
        $this->assertSame(2, $template->get_version());
        // Sharing is not content, so it makes no new version.
        $template = template::save((object) ['id' => $template->get_id(), 'enabled' => 0], $this->adminid);
        $this->assertSame(2, $template->get_version());
        $template = $template->restore_version(1, $this->adminid);
        $this->assertSame(3, $template->get_version());
        $this->assertSame('Version one', $template->get_body());
        $this->assertSame([3, 2, 1], array_map('intval', array_column($template->get_versions(), 'version')));
    }

    /**
     * Shipped templates can be copied, and the copy belongs to the user.
     */
    public function test_copy(): void {
        $user = $this->getDataGenerator()->create_user();
        $usercontext = \context_user::instance($user->id);
        $copy = template::find('mod_vocab:toeic-part1')->copy($usercontext->id, $user->id);
        $this->assertFalse($copy->is_shipped());
        $this->assertTrue($copy->can_edit($user->id));
        $this->assertSame('toeic-part1', $copy->get_shortname());
        $this->assertSame('Copy of TOEIC R&L (Part 1): Describe an image', $copy->get_name());
        $this->assertSame(template::find('tool_aibridge:generate-questions')->get_id(), $copy->get_parent()->get_id());
        $this->assertSame('toeic-part1-2', template::find('mod_vocab:toeic-part1')->copy($usercontext->id, $user->id)
            ->get_shortname());
    }

    /**
     * Templates are shared like providers.
     */
    public function test_sharing(): void {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $other = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $student = $gen->create_and_enrol($course, 'student');
        $coursecontext = \context_course::instance($course->id);
        $template = template::save((object) ['type' => 'fragment', 'name' => 'Course rules', 'body' => 'x',
            'ownerid' => $teacher->id, 'contextid' => $coursecontext->id], $teacher->id);
        $personal = template::save((object) ['type' => 'fragment', 'name' => 'My rules', 'body' => 'x',
            'ownerid' => $teacher->id, 'contextid' => \context_user::instance($teacher->id)->id], $teacher->id);

        $this->assertTrue($template->can_use($coursecontext, $student->id));
        $this->assertFalse($template->can_use(\context_course::instance($other->id), $student->id));
        $this->assertFalse($template->can_edit($student->id));
        $this->assertTrue($template->can_edit($teacher->id));
        $this->assertFalse($personal->can_use($coursecontext, $student->id));
        $this->assertTrue($personal->can_use(\context_course::instance($other->id), $teacher->id));
        $this->assertNull(library::find('my-rules', $coursecontext, $student->id));

        $names = array_map(function ($t) {
            return $t->get_shortname();
        }, template::get_available($coursecontext, $teacher->id, template::TYPE_FRAGMENT));
        $this->assertSame(['course-rules', 'my-rules'], array_slice(array_values($names), 0, 2));

        // When the course is deleted, the template becomes the teacher's own.
        delete_course($course, false);
        $this->assertEquals(\context_user::instance($teacher->id)->id, template::get($template->get_id())->get_record()->contextid);
    }

    /**
     * When a user is deleted, their personal templates and providers go, and their shared templates stay.
     */
    public function test_user_deleted(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $shared = template::save((object) ['type' => 'fragment', 'name' => 'Shared', 'body' => 'x',
            'ownerid' => $teacher->id, 'contextid' => \context_course::instance($course->id)->id], $teacher->id);
        $personal = template::save((object) ['type' => 'fragment', 'name' => 'Personal', 'body' => 'x',
            'ownerid' => $teacher->id, 'contextid' => \context_user::instance($teacher->id)->id], $teacher->id);
        $gen->get_plugin_generator('tool_aibridge')->create_provider(['ownerid' => $teacher->id,
            'contextid' => \context_user::instance($teacher->id)->id]);
        delete_user($teacher);
        $this->assertEquals(0, template::get($shared->get_id())->get_record()->ownerid);
        $this->assertFalse($DB->record_exists(template::TABLE, ['id' => $personal->get_id()]));
        $this->assertFalse($DB->record_exists(provider::TABLE, ['ownerid' => $teacher->id]));
    }

    /**
     * Templates can be exported and imported, with the links between them.
     */
    public function test_export_import(): void {
        $templates = [template::find('mod_vocab:toeic-part2'), template::find('mod_vocab:toeic-part2-format')];
        $json = library::export($templates);
        $this->assertStringContainsString('"extends": "tool_aibridge:generate-questions"', $json);
        $this->assertStringNotContainsString('apikey', $json);

        $user = $this->getDataGenerator()->create_user();
        $imported = library::import($json, \context_user::instance($user->id)->id, $user->id);
        $this->assertCount(2, $imported);
        $prompt = template::find('toeic-part2');
        $this->assertSame(['multichoice' => 'toeic-part2-format'], $prompt->get_formatlinks());
        $this->assertSame('tool_aibridge:generate-questions', $prompt->get_parent()->get_shortname());
        $this->assertSame(1, $prompt->get_version());
        $this->assertEquals($user->id, $prompt->get_record()->ownerid);

        $this->setUser($user);
        $usercontext = \context_user::instance($user->id);
        $rendering = library::render($prompt, $usercontext, $user->id, ['vocabulary-item' => 'x'], 'multichoice');
        $this->assertSame([], $rendering->get_errors());
        $this->assertStringContainsString('::Part-2.Q1::', $rendering->get_text());

        $this->expectException(\moodle_exception::class);
        library::import('{"something": "else"}', SYSCONTEXTID, $user->id);
    }

    /**
     * A rendered prompt is sent, logged with its template version, and the reply is read.
     */
    public function test_generate(): void {
        global $DB;
        set_config('coreroute', core_bridge::ROUTE_NEVER, 'tool_aibridge');
        set_config('requirepolicy', 0, 'tool_aibridge');
        $this->getDataGenerator()->get_plugin_generator('tool_aibridge')->create_provider();
        $fence = str_repeat(chr(96), 3);
        $gift = "Here are your questions:\n{$fence}\n" .
            "::Q1::Which word means 'to change the time of'? {\n=reschedule\n~reserve\n~remain\n}\n\n" .
            "::Q2::[[AUDIO class=\"nomediaplugin\" Can we reschedule?]] {\n=Yes #Right\n~No #Wrong\n}\n{$fence}\nGood luck!";
        \curl::mock_response(json_encode(['choices' => [['message' => ['content' => $gift]]]]));
        $rendering = $this->render('tool_aibridge:generate-questions', ['vocabulary-item' => 'reschedule']);
        [$response, $parsed] = library::generate($rendering, $this->context, $this->adminid, 'mod_test');
        $this->assertTrue($response->get_success(), $response->get_error());
        $this->assertSame([], $parsed->get_errors());
        $this->assertSame(2, $parsed->get_count());
        $this->assertStringStartsWith('::Q1::', $parsed->get_text());
        $this->assertCount(1, $parsed->get_media('AUDIO'));

        $log = $DB->get_record(logger::TABLE, ['id' => $response->get_logid()]);
        $this->assertEquals(template::find('tool_aibridge:generate-questions')->get_id(), $log->templateid);
        $this->assertEquals(1, $log->templateversion);
        $this->assertSame('tool_aibridge:generate-questions', $log->purpose);

        // An invalid rendering is not sent.
        [$response, $parsed] = library::generate(
            $this->render('tool_aibridge:generate-questions'),
            $this->context,
            $this->adminid
        );
        $this->assertSame('templateinvalid', $response->get_errorcode());
        $this->assertNull($parsed);
    }
}
