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

#[\PHPUnit\Framework\Attributes\CoversClass(\tool_aibridge\media_converter::class)]
/**
 * Tests for converting media files.
 *
 * @package    tool_aibridge
 * @category   test
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_aibridge\media_converter
 */
final class media_converter_test extends \advanced_testcase {
    /**
     * Make a PNG image with some detail, so that it is not tiny.
     *
     * @param int $width
     * @param int $height
     * @return string
     */
    protected function make_png(int $width, int $height): string {
        $image = imagecreatetruecolor($width, $height);
        // Random colours, like a photograph, which PNG cannot make much smaller.
        mt_srand(42);
        for ($x = 0; $x < $width; $x += 2) {
            for ($y = 0; $y < $height; $y += 2) {
                $colour = imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
                imagefilledrectangle($image, $x, $y, $x + 1, $y + 1, $colour);
            }
        }
        ob_start();
        imagepng($image);
        imagedestroy($image);
        return ob_get_clean();
    }

    /**
     * Images are made smaller and converted to JPEG, as the site's settings say.
     */
    public function test_convert_image(): void {
        $this->resetAfterTest();
        if (!function_exists('imagecreatefromstring')) {
            $this->markTestSkipped('GD is not available.');
        }
        $png = $this->make_png(1600, 800);

        // The defaults: JPEG, at most 1024 pixels.
        [$content, $extension, $mimetype] = media_converter::convert_content($png, 'png', 'image/png');
        $this->assertSame('jpg', $extension);
        $this->assertSame('image/jpeg', $mimetype);
        $this->assertSame([1024, 512], array_slice(getimagesizefromstring($content), 0, 2));
        $this->assertLessThan(strlen($png), strlen($content));

        // Keep the format, at most 400 pixels.
        set_config('imageformat', media_converter::FORMAT_KEEP, 'tool_aibridge');
        set_config('imagemaxside', 400, 'tool_aibridge');
        [$content, $extension] = media_converter::convert_content($png, 'png', 'image/png');
        $this->assertSame('png', $extension);
        $this->assertSame([400, 200], array_slice(getimagesizefromstring($content), 0, 2));

        // Nothing to do.
        set_config('imagemaxside', 0, 'tool_aibridge');
        $this->assertNull(media_converter::convert_content($png, 'png', 'image/png'));

        // Other options than the site's.
        [, $extension] = media_converter::convert_content($png, 'png', 'image/png', ['imageformat' => 'jpg']);
        $this->assertSame('jpg', $extension);
    }

    /**
     * A response's image is converted, and a generated image is converted when it is made.
     */
    public function test_convert_media(): void {
        $this->resetAfterTest();
        if (!function_exists('imagecreatefromstring')) {
            $this->markTestSkipped('GD is not available.');
        }
        set_config('requirepolicy', 0, 'tool_aibridge');
        $png = $this->make_png(1200, 1200);
        $response = response::success(response::ROUTE_EXTERNAL, 1, 'Test')->set_image($png, 'image/png', 'png');
        $this->assertTrue($response->convert_media());
        $this->assertSame('jpg', $response->get_extension());
        $this->assertSame(1024, getimagesizefromstring($response->get_image())[0]);
        // Converting again would not make it smaller, so it is kept.
        $this->assertFalse($response->convert_media(['imagemaxside' => 0]));

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->get_plugin_generator('tool_aibridge')->create_provider(['capability' => 'image']);
        \curl::mock_response(json_encode(['data' => [['b64_json' => base64_encode($png)]]]));
        $item = ['type' => 'IMAGE', 'text' => 'A cat', 'attributes' => '', 'marker' => '[[IMAGE A cat]]'];
        $response = media::generate($item, \context_user::instance($user->id)->id, $user->id);
        $this->assertTrue($response->get_success(), $response->get_error());
        $this->assertSame('jpg', $response->get_extension());

        \curl::mock_response(json_encode(['data' => [['b64_json' => base64_encode($png)]]]));
        $response = media::generate($item, \context_user::instance($user->id)->id, $user->id, ['convert' => false]);
        $this->assertSame('png', $response->get_extension());
    }

    /**
     * Audio is converted to MP3 with ffmpeg, if the server has it; otherwise it is kept.
     */
    public function test_convert_audio(): void {
        $this->resetAfterTest();
        $wav = audio::pcm_to_wav(str_repeat("\x10\x00\xF0\xFF", 24000), 24000);
        if (media_converter::get_ffmpeg() === null) {
            $this->assertNull(media_converter::convert_content($wav, 'wav', 'audio/wav'));
            $this->markTestSkipped('ffmpeg is not available.');
        }
        [$content, $extension, $mimetype] = media_converter::convert_content($wav, 'wav', 'audio/wav');
        $this->assertSame('mp3', $extension);
        $this->assertSame(mimeinfo('type', 'file.mp3'), $mimetype);
        $this->assertLessThan(strlen($wav), strlen($content));
        $this->assertNull(media_converter::convert_content($wav, 'wav', 'audio/wav', ['audioformat' => 'keep']));
    }

    /**
     * The status lists the tools.
     */
    public function test_get_status_html(): void {
        $html = media_converter::get_status_html();
        $this->assertStringContainsString('ffmpeg', $html);
        $this->assertStringContainsString('GD', $html);
    }
}
