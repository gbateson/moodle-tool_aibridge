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
 * Tests for working out what kind of audio a provider sent back.
 *
 * @package    tool_aibridge
 * @category   test
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_aibridge\audio::class)]
final class audio_test extends \advanced_testcase {
    /**
     * Audio formats are recognised from their first bytes.
     */
    public function test_identify(): void {
        $this->assertSame('mp3', audio::identify('ID3' . str_repeat("\0", 20))[1]);
        $this->assertSame('mp3', audio::identify("\xFF\xFB" . str_repeat("\0", 20))[1]);
        $this->assertSame('ogg', audio::identify('OggS' . str_repeat("\0", 20))[1]);
        $this->assertSame('wav', audio::identify('RIFF1234WAVEfmt ')[1]);
    }

    /**
     * Raw PCM samples are given a WAV header.
     */
    public function test_pcm(): void {
        [$wav, $extension, $mimetype] = audio::identify(str_repeat("\x01\x00", 100), 'audio/L16;codec=pcm;rate=24000');
        $this->assertSame('wav', $extension);
        $this->assertSame('audio/wav', $mimetype);
        $this->assertSame(244, strlen($wav));
        $this->assertSame(24000, unpack('V', substr($wav, 24, 4))[1]);
    }

    /**
     * Text is not audio.
     */
    public function test_not_audio(): void {
        $this->expectException(\moodle_exception::class);
        audio::identify('{"error": "oops"}');
    }

    /**
     * The MIME type is found next to the audio in a JSON reply.
     */
    public function test_find_mimetype(): void {
        $decoded = ['parts' => [['inlineData' => ['mimeType' => 'audio/L16;rate=24000', 'data' => 'xx']]]];
        $this->assertSame('audio/L16;rate=24000', audio::find_mimetype($decoded, 'parts,0,inlineData,data'));
        $this->assertSame('', audio::find_mimetype($decoded, 'parts,0'));
    }
}
