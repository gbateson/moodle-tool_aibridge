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
 * Tests for the caller, which fills in request templates and reads replies.
 *
 * @package    tool_aibridge
 * @category   test
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_aibridge\caller::class)]
final class caller_test extends \advanced_testcase {
    /**
     * Tokens are JSON-escaped in the body, and inserted as they are in URLs and headers.
     */
    public function test_substitute(): void {
        $template = '{"text": "{{PROMPT}}", "max": {{MAX}}}';
        $body = caller::substitute_escaped($template, ['PROMPT' => "Say \"hi\"\nand é/ü", 'MAX' => '100']);
        $decoded = json_decode($body, true);
        $this->assertSame("Say \"hi\"\nand é/ü", $decoded['text']);
        $this->assertSame(100, $decoded['max']);

        $url = caller::substitute_plain('https://x.test/{{MODEL}}?key={{APIKEY}}', ['MODEL' => 'm-1', 'APIKEY' => 'k']);
        $this->assertSame('https://x.test/m-1?key=k', $url);
    }

    /**
     * Invalid UTF-8 does not empty the value.
     */
    public function test_substitute_invalid_utf8(): void {
        $body = caller::substitute_escaped('{"t": "{{T}}"}', ['T' => "ab\xC3"]);
        $decoded = json_decode($body, true);
        $this->assertStringStartsWith('ab', $decoded['t']);
    }

    /**
     * Values are found by their path in a reply.
     */
    public function test_extract_by_path(): void {
        $data = ['choices' => [['message' => ['content' => 'Hello']]]];
        $this->assertSame('Hello', caller::extract_by_path($data, 'choices,0,message,content'));
        $this->assertSame('Hello', caller::extract_by_path($data, 'choices, 0, message, content'));
        $this->assertNull(caller::extract_by_path($data, 'choices,1,message'));
        $this->assertNull(caller::extract_by_path($data, ''));
    }

    /**
     * API keys are removed from messages.
     */
    public function test_hide_key(): void {
        $this->assertSame('bad key ******** here', caller::hide_key('bad key sk-123 here', 'sk-123'));
        $this->assertSame('no key', caller::hide_key('no key', ''));
    }

    /**
     * Token usage is found in replies from OpenAI, Anthropic and Gemini.
     */
    public function test_find_token_usage(): void {
        $this->assertSame([10, 20], caller::find_token_usage(['usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20]]));
        $this->assertSame([11, 21], caller::find_token_usage(['usage' => ['input_tokens' => 11, 'output_tokens' => 21]]));
        $this->assertSame([12, 22], caller::find_token_usage(
            ['usageMetadata' => ['promptTokenCount' => 12, 'candidatesTokenCount' => 22]]
        ));
        $this->assertSame([null, null], caller::find_token_usage(['other' => 1]));
    }

    /**
     * The file part is chosen by MIME type.
     */
    public function test_choose_file_part(): void {
        $single = '{"inline_data": {"data": "{{FILE_BASE64}}"}}';
        $this->assertSame($single, caller::choose_file_part($single, 'audio/mpeg'));

        $map = '{"image/*": {"type": "image"}, "application/pdf": {"type": "pdf"}, "*": {"type": "other"}}';
        $this->assertSame('{"type":"image"}', caller::choose_file_part($map, 'image/png'));
        $this->assertSame('{"type":"pdf"}', caller::choose_file_part($map, 'application/pdf'));
        $this->assertSame('{"type":"other"}', caller::choose_file_part($map, 'audio/wav'));

        $this->expectException(\moodle_exception::class);
        caller::choose_file_part('{"image/*": {"type": "image"}}', 'audio/wav');
    }

    /**
     * Files are sent as parts of the request.
     */
    public function test_send_with_files(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $preset = presets::get_presets()['geminifiles'];
        $provider = provider::save((object) ($preset + [
            'ownerid' => $user->id,
            'contextid' => \context_user::instance($user->id)->id,
        ]), 'secret');
        $fs = get_file_storage();
        $file = $fs->create_file_from_string([
            'contextid' => \context_user::instance($user->id)->id, 'component' => 'user', 'filearea' => 'draft',
            'itemid' => 1, 'filepath' => '/', 'filename' => 'a.mp3', 'mimetype' => 'audio/mpeg',
        ], 'ID3audio');
        \curl::mock_response('{"candidates": [{"content": {"parts": [{"text": "ok"}]}}]}');
        $info = [];
        $reply = caller::send($provider, ['PROMPT' => 'Listen'], [$file], $info);
        $this->assertStringContainsString('"ok"', $reply);
    }

    /**
     * A token in the provider's templates that has no value stops the request,
     * and providers with no default values get those of the preset they match.
     */
    public function test_missing_tokens(): void {
        $this->resetAfterTest();
        $this->assertSame(['VOICE'], caller::find_missing_tokens(['{"v": "{{VOICE}}", "t": "{{TEXT}}"}', '{{FILE_PARTS}}'],
            ['TEXT' => 'Hi']));

        $user = $this->getDataGenerator()->create_user();
        $preset = presets::get_presets()['geminitts'];
        $preset['tokendefaults'] = '';
        $provider = provider::save((object) ($preset + [
            'ownerid' => $user->id,
            'contextid' => \context_user::instance($user->id)->id,
        ]), 'secret');
        try {
            caller::send($provider, ['TEXT' => 'Hello']);
            $this->fail('The request should not be sent.');
        } catch (\moodle_exception $e) {
            $this->assertSame('errormissingtokens', $e->errorcode);
            $this->assertStringContainsString('VOICE', $e->getMessage());
        }

        $this->assertSame('geminitts', presets::find_match($provider->get_record()));
        $this->assertSame(1, presets::add_missing_defaults());
        $this->assertSame('Kore', provider::get($provider->get_id())->get_token_defaults()['VOICE']);
        $this->assertSame(0, presets::add_missing_defaults());
    }
}
