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

#[\PHPUnit\Framework\Attributes\CoversClass(\tool_aibridge\audio::class)]
/**
 * Tests for working out what kind of audio a provider sent back.
 *
 * @package    tool_aibridge
 * @category   test
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_aibridge\audio
 */
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
     * The length of audio, and subtitles for pieces of audio.
     */
    public function test_duration_and_captions(): void {
        $wav = audio::pcm_to_wav(str_repeat("\x01\x00", 24000), 24000);
        $this->assertEqualsWithDelta(1.0, audio::get_duration($wav, 'wav'), 0.0001);
        // A 128 kbit/s MPEG-1 Layer III frame header, then 15996 more bytes: 16000 bytes last 1 second.
        $mp3 = "\xFF\xFB\x90\x00" . str_repeat("\x00", 15996);
        $this->assertEqualsWithDelta(1.0, audio::get_duration($mp3, 'mp3'), 0.0001);
        $this->assertSame(0.0, audio::get_duration('text', 'mp3'));
        $this->assertSame(0.0, audio::get_duration('text', 'wav'));

        $captions = audio::make_captions([[1.5, 'A: Hello.'], [62.25, "B: Good\n morning."]], 500);
        $this->assertSame("WEBVTT\n\n1\n00:00:00.000 --> 00:00:01.500\nA: Hello.\n\n" .
            "2\n00:00:02.000 --> 00:01:04.250\nB: Good morning.\n", $captions);
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
