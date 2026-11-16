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

#[\PHPUnit\Framework\Attributes\CoversClass(\tool_aibridge\response::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_aibridge\upload\uploader::class)]
/**
 * Tests for responses, and for uploading files before a request.
 *
 * @package    tool_aibridge
 * @category   test
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_aibridge\response
 * @covers     \tool_aibridge\upload\uploader
 */
final class response_test extends \advanced_testcase {
    /**
     * Failures that may not happen again are temporary, so that tasks can try again.
     */
    public function test_is_temporary(): void {
        $this->assertFalse(response::success(response::ROUTE_EXTERNAL, 1, 'A')->is_temporary());

        $codes = [0 => true, 408 => true, 429 => true, 500 => true, 503 => true, 400 => false, 401 => false, 404 => false];
        foreach ($codes as $code => $temporary) {
            $response = response::from_exception(new request_exception('HTTP ' . $code, $code), 'HTTP ' . $code);
            $this->assertSame('request', $response->get_errorcode());
            $this->assertSame($code, $response->get_httpcode());
            $this->assertSame($temporary, $response->is_temporary(), 'HTTP ' . $code);
        }

        $this->assertTrue(response::failure('replynotjson')->is_temporary());
        $this->assertTrue(response::failure('userratelimit')->is_temporary());
        $this->assertFalse(response::failure('policynotaccepted')->is_temporary());
        $this->assertFalse(response::failure('noproviders')->is_temporary());
        $this->assertTrue(response::failure('core')->set_httpcode(503)->is_temporary());
        $this->assertFalse(response::failure('core')->set_httpcode(401)->is_temporary());
    }

    /**
     * Files are uploaded first, with a provider's own way of uploading.
     */
    public function test_uploader(): void {
        $this->resetAfterTest();
        $this->assertInstanceOf(upload\gemini::class, upload\uploader::create(upload\uploader::PROTOCOL_GEMINI));
        $this->assertArrayHasKey(upload\uploader::PROTOCOL_GEMINI, upload\uploader::get_protocol_options());
        $this->expectException(\moodle_exception::class);
        upload\uploader::create('nosuchprotocol');
    }

    /**
     * The large-file preset uploads files first, and refers to them by URI.
     */
    public function test_upload_preset(): void {
        $preset = presets::get_presets()['geminiupload'];
        $this->assertSame(provider::UPLOAD_REFERENCE, $preset['uploadstyle']);
        $this->assertStringContainsString('{{FILE_URI}}', $preset['filetemplate']);
        $this->assertStringNotContainsString('APIKEY', $preset['uploadurl']);
    }
}
