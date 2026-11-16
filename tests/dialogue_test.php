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

#[\PHPUnit\Framework\Attributes\CoversClass(\tool_aibridge\dialogue::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_aibridge\audio::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_aibridge\media::class)]
/**
 * Tests for conversations, joining audio, and making media from markers.
 *
 * @package    tool_aibridge
 * @category   test
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_aibridge\dialogue
 * @covers     \tool_aibridge\audio
 * @covers     \tool_aibridge\media
 */
final class dialogue_test extends \advanced_testcase {
    /** A conversation, as in the TOEIC Part 3 format. */
    const SCRIPT = "
            A=woman (customer calling with complaint)
            B=male (staff on a customer help line)
            A: Hello. I'm calling about a coffee machine I purchased from your website.
            B: Oh, I'm sorry to hear that. Do you know when you bought it?
            A: I've had it for over a year
               so the warranty has probably just expired.
            B: Well, I can offer you a coupon for 40% off your next purchase.
        ";

    /**
     * A script is read: its speakers, with gender and role, and its lines.
     */
    public function test_parse(): void {
        $parsed = dialogue::parse(self::SCRIPT);
        $this->assertSame(['gender' => 'female', 'role' => 'customer calling with complaint'], $parsed['speakers']['A']);
        $this->assertSame('male', $parsed['speakers']['B']['gender']);
        $this->assertCount(4, $parsed['lines']);
        $this->assertSame(['A', "I've had it for over a year so the warranty has probably just expired."], $parsed['lines'][2]);

        // Speakers without a header, and names rather than letters.
        $parsed = dialogue::parse("Ken: Hi!\nMari: Hello, Ken.");
        $this->assertSame(['Ken', 'Mari'], array_keys($parsed['speakers']));
        $this->assertSame('', $parsed['speakers']['Ken']['gender']);

        $this->assertTrue(dialogue::is_dialogue(self::SCRIPT));
        $this->assertFalse(dialogue::is_dialogue('Who should I give the report to?'));
        $this->assertFalse(dialogue::is_dialogue('Note: the shop is closed.'));
    }

    /**
     * Each speaker gets a different voice, by gender.
     */
    public function test_choose_voices(): void {
        $defaults = ['VOICES_FEMALE' => 'nova,shimmer', 'VOICES_MALE' => 'onyx,echo'];
        $speakers = ['A' => ['gender' => 'female', 'role' => ''], 'B' => ['gender' => 'male', 'role' => ''],
            'C' => ['gender' => 'female', 'role' => ''], 'D' => ['gender' => '', 'role' => '']];
        $this->assertSame(
            ['A' => 'nova', 'B' => 'onyx', 'C' => 'shimmer', 'D' => 'echo'],
            dialogue::choose_voices($speakers, $defaults)
        );
        // Chosen voices are kept, and not used again.
        $this->assertSame(
            ['A' => 'shimmer', 'B' => 'onyx', 'C' => 'nova', 'D' => 'echo'],
            dialogue::choose_voices($speakers, $defaults, ['A' => 'shimmer'])
        );
        // No lists: the provider's default voice.
        $this->assertSame(['A' => '', 'B' => '', 'C' => '', 'D' => ''], dialogue::choose_voices($speakers, []));
    }

    /**
     * WAV pieces are joined with pauses; MP3 pieces without their tags.
     */
    public function test_join(): void {
        $one = audio::pcm_to_wav(str_repeat("\x01\x00", 100), 24000);
        $two = audio::pcm_to_wav(str_repeat("\x02\x00", 50), 24000);
        [$wav, $extension] = audio::join([[$one, 'wav', 'audio/wav'], [$two, 'wav', 'audio/wav']], 100);
        $this->assertSame('wav', $extension);
        [$format, $samples] = audio::read_wav($wav);
        // 200 + 100 bytes of samples, and 0.1 second of silence at 24000 samples of 2 bytes.
        $this->assertSame(200 + 4800 + 100, strlen($samples));
        $this->assertSame(24000, $format['rate']);

        // A WAV file whose data size is unknown, as streamed by some APIs.
        $streamed = substr_replace($one, pack('V', 0xFFFFFFFF), 40, 4);
        $this->assertSame(200, strlen(audio::read_wav($streamed)[1]));

        $tagged = 'ID3' . "\x04\x00\x00" . "\x00\x00\x00\x05" . 'xxxxx' . "\xFF\xFBframe";
        [$mp3] = audio::join([[$tagged, 'mp3', 'audio/mpeg'], [$tagged, 'mp3', 'audio/mpeg']]);
        $this->assertSame("\xFF\xFBframe\xFF\xFBframe", $mp3);

        $this->expectException(\moodle_exception::class);
        audio::join([[$one, 'wav', 'audio/wav'], [audio::pcm_to_wav('xx', 16000), 'wav', 'audio/wav']]);
    }

    /**
     * A conversation is spoken line by line, in different voices, and joined.
     */
    public function test_generate(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('requirepolicy', 0, 'tool_aibridge');
        $user = $this->getDataGenerator()->create_user();
        $context = \context_user::instance($user->id);
        $provider = $this->getDataGenerator()->get_plugin_generator('tool_aibridge')->create_provider(['capability' => 'speech']);
        $wav = audio::pcm_to_wav(str_repeat("\x01\x00", 100), 24000);
        for ($i = 0; $i < 4; $i++) {
            \curl::mock_response($wav);
        }
        $response = dialogue::generate($context->id, $user->id, self::SCRIPT, ['component' => 'mod_test', 'pause' => 0]);
        $this->assertTrue($response->get_success(), $response->get_error());
        $this->assertSame('wav', $response->get_extension());
        $this->assertSame(800, strlen(audio::read_wav($response->get_audio())[1]));
        $this->assertSame('A: nova, B: onyx', $response->get_content());
        $this->assertSame(4, $DB->count_records(logger::TABLE, ['component' => 'mod_test', 'success' => 1]));

        // Each line is a subtitle: 100 samples at 24000 Hz last 4 ms.
        $captions = $response->get_captions();
        $this->assertStringStartsWith("WEBVTT\n\n1\n00:00:00.000 --> 00:00:00.004\nA: ", $captions);
        $this->assertStringContainsString("\n4\n00:00:00.01", $captions);

        // The voice, role and format are tokens of each line.
        $action = new action\generate_speech($context->id, $user->id, 'Hello', 'nova');
        $action->set_token('AUDIOFORMAT', 'wav');
        $tokens = array_merge($provider->get_token_defaults(), $action->get_tokens($provider));
        $this->assertSame('wav', $tokens['AUDIOFORMAT']);
        $this->assertSame('mp3', $provider->get_token_defaults()['AUDIOFORMAT']);

        // A failed line stops the conversation.
        \curl::mock_response('not audio');
        $response = dialogue::generate($context->id, $user->id, "A: Hi.\nB: Hello.");
        $this->assertFalse($response->get_success());
        $this->assertStringContainsString('Line 1', $response->get_error());

        $this->assertSame('nodialogue', dialogue::generate($context->id, $user->id, 'Hello')->get_errorcode());
    }

    /**
     * Markers become speech, conversations or images.
     */
    public function test_media(): void {
        $this->resetAfterTest();
        set_config('requirepolicy', 0, 'tool_aibridge');
        set_config('coreroute', core_bridge::ROUTE_NEVER, 'tool_aibridge');
        $user = $this->getDataGenerator()->create_user();
        $contextid = \context_user::instance($user->id)->id;
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_aibridge');
        $generator->create_provider(['capability' => 'speech']);
        $generator->create_provider(['capability' => 'image']);
        $items = library\parser::find_media("[[AUDIO class=\"nomediaplugin\" Who should I give the report to?]]\n" .
            "[[IMAGE A swan by a pond.]]\n" .
            "[[AUDIO class=\"nomediaplugin\"\n A=woman\n B=man\n A: Hi.\n B: Hello.\n]]\n" .
            "[[VIDEO A swan.]]");

        \curl::mock_response('ID3' . str_repeat("\0", 20));
        $response = media::generate($items[0], $contextid, $user->id);
        $this->assertSame('mp3', $response->get_extension());

        \curl::mock_response(json_encode(['data' => [['b64_json' => base64_encode("\x89PNG" . str_repeat("\0", 20))]]]));
        $response = media::generate($items[1], $contextid, $user->id, ['aspectratio' => 'landscape']);
        $this->assertSame('png', $response->get_extension());

        $wav = audio::pcm_to_wav('abcd', 24000);
        \curl::mock_response($wav);
        \curl::mock_response($wav);
        $response = media::generate($items[2], $contextid, $user->id);
        $this->assertTrue($response->get_success(), $response->get_error());
        $this->assertSame('A: nova, B: onyx', $response->get_content());

        $this->assertSame('mediatype', media::generate($items[3], $contextid, $user->id)->get_errorcode());
    }
}
