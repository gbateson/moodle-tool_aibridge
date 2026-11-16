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
 * Make media files smaller, or convert them to other formats.
 *
 * - Audio: ffmpeg, as mono MP3, which is plenty for speech.
 * - Video: ffmpeg, as MP4 (H.264), at most 1280 pixels wide or tall (720p),
 *   or only its sound, as MP3.
 * - Images: PHP's GD, as JPEG or PNG, at most a given number of pixels wide or tall.
 * - PDFs: Ghostscript (Moodle's "Path to ghostscript" setting), with images at 150 dpi.
 *
 * The site's settings say how the media that AI providers make, e.g. images
 * and speech, is converted (see get_options()). Other plugins can use the
 * methods with options of their own, e.g. to make students' large uploads
 * smaller. If a tool is missing, or PHP cannot run programs, files are kept
 * as they are.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class media_converter {
    /** Keep files in the format that they are in. */
    const FORMAT_KEEP = 'keep';

    /** The default path to ffmpeg. */
    const DEFAULT_FFMPEG = '/usr/bin/ffmpeg';

    /** The default longest side of images that AI providers make, in pixels. */
    const DEFAULT_IMAGE_MAX_SIDE = 1024;

    /** The default format of images that AI providers make. */
    const DEFAULT_IMAGE_FORMAT = 'jpg';

    /** The default quality of JPEG images, 0 to 100. */
    const DEFAULT_IMAGE_QUALITY = 80;

    /** The default format of audio that AI providers make. */
    const DEFAULT_AUDIO_FORMAT = 'mp3';

    /** The default bit rate of audio, mono. */
    const DEFAULT_AUDIO_BITRATE = '48k';

    /** The bit rates that can be chosen. */
    const AUDIO_BITRATES = ['32k', '48k', '64k', '96k', '128k'];

    /** The longest side of a video, in pixels (720p). */
    const VIDEO_MAX_SIDE = 1280;

    /** The most pixels an image may have to be converted here (larger ones would use too much memory). */
    const IMAGE_MAX_PIXELS = 60000000;

    /** Image types that GD can convert. (Not GIF, which may be animated.) */
    const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/bmp'];

    /** The longest a program may run, in seconds. */
    const TIMEOUT = 900;

    /** @var string why the last program that was run failed, or ''. */
    protected static $lasterror = '';

    /**
     * Get why the last program that was run failed, e.g. for a task's output.
     *
     * @return string '' if it did not fail.
     */
    public static function get_last_error(): string {
        return self::$lasterror;
    }

    /**
     * Get the options for the media that AI providers make, from the site's settings.
     *
     * @return array imageformat, imagemaxside, imagequality, audioformat, audiobitrate.
     */
    public static function get_options(): array {
        $config = get_config('tool_aibridge');
        $get = function (string $name, $default) use ($config) {
            return (isset($config->$name) && $config->$name !== '') ? $config->$name : $default;
        };
        return [
            'imageformat' => (string) $get('imageformat', self::DEFAULT_IMAGE_FORMAT),
            'imagemaxside' => (int) $get('imagemaxside', self::DEFAULT_IMAGE_MAX_SIDE),
            'imagequality' => (int) $get('imagequality', self::DEFAULT_IMAGE_QUALITY),
            'audioformat' => (string) $get('audioformat', self::DEFAULT_AUDIO_FORMAT),
            'audiobitrate' => (string) $get('audiobitrate', self::DEFAULT_AUDIO_BITRATE),
        ];
    }

    /**
     * Can PHP run programs?
     *
     * @return bool
     */
    public static function can_exec(): bool {
        if (!function_exists('exec')) {
            return false;
        }
        $disabled = array_map('trim', explode(',', strtolower((string) ini_get('disable_functions'))));
        return !in_array('exec', $disabled);
    }

    /**
     * Get the path to ffmpeg, if it can be run.
     *
     * @return string|null
     */
    public static function get_ffmpeg(): ?string {
        $path = get_config('tool_aibridge', 'pathtoffmpeg');
        return self::usable(($path === false) ? self::DEFAULT_FFMPEG : (string) $path);
    }

    /**
     * Get the path to Ghostscript, from Moodle's own setting, if it can be run.
     *
     * @return string|null
     */
    public static function get_ghostscript(): ?string {
        global $CFG;
        return self::usable((string) ($CFG->pathtogs ?? ''));
    }

    /**
     * Get a program's path, if it can be run.
     *
     * @param string $path
     * @return string|null
     */
    protected static function usable(string $path): ?string {
        $path = trim($path);
        if ($path === '' || !self::can_exec() || !is_file($path) || !is_executable($path)) {
            return null;
        }
        return $path;
    }

    /**
     * Describe what can be converted on this site, for settings pages.
     *
     * @return string HTML
     */
    public static function get_status_html(): string {
        $conditions = [
            'exec' => self::can_exec(),
            'ffmpeg' => self::get_ffmpeg() !== null,
            'ghostscript' => self::get_ghostscript() !== null,
            'gd' => function_exists('imagecreatefromstring'),
        ];
        $items = '';
        foreach ($conditions as $name => $met) {
            $icon = $met ? '✔' : '✘';
            $class = $met ? 'text-success' : 'text-danger';
            $items .= \html_writer::tag('li', \html_writer::span($icon, $class . ' me-1 mr-1') .
                    get_string('convertcondition_' . $name, 'tool_aibridge'));
        }
        return \html_writer::tag('ul', $items, ['class' => 'list-unstyled']);
    }

    /**
     * Convert a file on disk.
     *
     * @param string $input the file's path.
     * @param string $mimetype the file's type, e.g. "audio/wav".
     * @param string $basename the name of the new file, without its extension.
     * @param array $options
     *      audioonly (bool): for a video, take out only the sound;
     *      audioformat ("mp3" or "keep") and audiobitrate (e.g. "48k"), for audio;
     *      videoformat ("mp4" or "keep"), for video;
     *      imageformat ("jpg", "png" or "keep"), imagemaxside (pixels, 0 for any size)
     *      and imagequality (0 to 100), for images; reencode (bool): make images again even if
     *      their format and size are right, e.g. to lower the quality of large photos;
     *      pdf (bool): make PDFs smaller;
     *      minsaving (float): keep the new file only if it is at most this fraction
     *      of the original's size, e.g. 0.8, or 0 to keep it whatever its size.
     * @return array|null [path, filename, mimetype] of the new file, in a
     *      temporary folder, or null if nothing was done or it did not work.
     */
    public static function convert_path(string $input, string $mimetype, string $basename, array $options = []): ?array {
        $options += self::get_options() + [
            'audioonly' => false,
            'videoformat' => 'mp4',
            'pdf' => true,
            'reencode' => false,
            'minsaving' => 0,
        ];
        $isaudio = (strpos($mimetype, 'audio/') === 0);
        $isvideo = (strpos($mimetype, 'video/') === 0);
        $audioonly = $isvideo && !empty($options['audioonly']);
        $bitrate = self::DEFAULT_AUDIO_BITRATE;
        if (in_array($options['audiobitrate'], self::AUDIO_BITRATES)) {
            $bitrate = $options['audiobitrate'];
        }

        $dir = make_request_directory();
        if (($isaudio && $options['audioformat'] === 'mp3') || $audioonly) {
            $output = $dir . '/output.mp3';
            $done = self::run_ffmpeg(['-i', $input, '-vn', '-ac', '1', '-c:a', 'libmp3lame', '-b:a', $bitrate, $output]);
        } else if ($isvideo && $options['videoformat'] === 'mp4') {
            // At most 1280 pixels on the longest side, for landscape or portrait videos,
            // with even sizes, as H.264 needs.
            $output = $dir . '/output.mp4';
            $side = self::VIDEO_MAX_SIDE;
            $scale = "scale='if(gte(iw,ih),min($side,iw),-2)':'if(gte(iw,ih),-2,min($side,ih))'";
            $done = self::run_ffmpeg(['-i', $input, '-vf', $scale, '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '28',
                    '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-b:a', '64k', '-ac', '1', '-movflags', '+faststart', $output]);
        } else if (
            in_array($mimetype, self::IMAGE_TYPES) && (!empty($options['reencode'])
                || self::wants_image_change($input, $mimetype, $options))
        ) {
            $extension = ($options['imageformat'] === self::FORMAT_KEEP) ? self::get_image_extension($mimetype)
                : (($options['imageformat'] === 'png') ? 'png' : 'jpg');
            $output = $dir . '/output.' . $extension;
            $done = self::convert_image($input, $output, $mimetype, (int) $options['imagemaxside'], (int) $options['imagequality']);
        } else if ($mimetype === 'application/pdf' && !empty($options['pdf'])) {
            $output = $dir . '/output.pdf';
            $done = self::run_ghostscript($input, $output);
        } else {
            return null;
        }

        clearstatcache();
        if (!$done || !is_file($output) || filesize($output) <= 0) {
            return null;
        }
        $minsaving = (float) $options['minsaving'];
        if (!$audioonly && $minsaving > 0 && filesize($output) > filesize($input) * $minsaving) {
            return null;
        }
        $extension = pathinfo($output, PATHINFO_EXTENSION);
        return [$output, $basename . '.' . $extension, mimeinfo('type', 'file.' . $extension)];
    }

    /**
     * Convert a stored file, e.g. a student's upload.
     *
     * @param \stored_file $file
     * @param array $options see convert_path().
     * @return array|null [path, filename, mimetype] of the new file, in a temporary folder, or null.
     */
    public static function convert_file(\stored_file $file, array $options = []): ?array {
        $dir = make_request_directory();
        $input = $dir . '/input.' . (pathinfo($file->get_filename(), PATHINFO_EXTENSION) ?: 'bin');
        if (!$file->copy_content_to($input)) {
            return null;
        }
        $basename = pathinfo($file->get_filename(), PATHINFO_FILENAME);
        return self::convert_path($input, (string) $file->get_mimetype(), $basename, $options);
    }

    /**
     * Convert media that is in memory, e.g. what an AI provider sent back.
     *
     * @param string $content
     * @param string $extension e.g. "wav".
     * @param string $mimetype e.g. "audio/wav".
     * @param array $options see convert_path().
     * @return array|null [content, extension, mimetype], or null if nothing was done.
     */
    public static function convert_content(string $content, string $extension, string $mimetype, array $options = []): ?array {
        $dir = make_request_directory();
        $input = $dir . '/input.' . ($extension ?: 'bin');
        if (file_put_contents($input, $content) === false) {
            return null;
        }
        $result = self::convert_path($input, $mimetype, 'output', $options);
        if (!$result) {
            return null;
        }
        [$path, $filename, $newmimetype] = $result;
        return [file_get_contents($path), pathinfo($filename, PATHINFO_EXTENSION), $newmimetype];
    }

    /**
     * Would an image change, i.e. is it in another format, or too big?
     *
     * @param string $input
     * @param string $mimetype
     * @param array $options
     * @return bool
     */
    protected static function wants_image_change(string $input, string $mimetype, array $options): bool {
        $format = $options['imageformat'];
        if ($format !== self::FORMAT_KEEP && $format !== self::get_image_extension($mimetype)) {
            return true;
        }
        $maxside = (int) $options['imagemaxside'];
        $size = @getimagesize($input);
        return $maxside > 0 && $size && max($size[0], $size[1]) > $maxside;
    }

    /**
     * Get the usual extension of an image type.
     *
     * @param string $mimetype
     * @return string e.g. "jpg".
     */
    protected static function get_image_extension(string $mimetype): string {
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/bmp' => 'bmp'];
        return $extensions[$mimetype] ?? '';
    }

    /**
     * Run ffmpeg.
     *
     * @param array $arguments
     * @return bool whether it worked.
     */
    protected static function run_ffmpeg(array $arguments): bool {
        $ffmpeg = self::get_ffmpeg();
        if ($ffmpeg === null) {
            return false;
        }
        return self::run(array_merge([$ffmpeg, '-nostdin', '-hide_banner', '-loglevel', 'error', '-y'], $arguments));
    }

    /**
     * Make a PDF smaller with Ghostscript, with its images at 150 dpi.
     *
     * @param string $input
     * @param string $output
     * @return bool whether it worked.
     */
    protected static function run_ghostscript(string $input, string $output): bool {
        $gs = self::get_ghostscript();
        if ($gs === null) {
            return false;
        }
        return self::run([$gs, '-sDEVICE=pdfwrite', '-dCompatibilityLevel=1.5', '-dPDFSETTINGS=/ebook',
                '-dNOPAUSE', '-dQUIET', '-dBATCH', '-dSAFER', '-sOutputFile=' . $output, $input]);
    }

    /**
     * Run a program, at low priority and with a time limit, if the server
     * has "nice" and "timeout".
     *
     * @param array $command the program and its arguments.
     * @return bool whether it worked.
     */
    protected static function run(array $command): bool {
        $prefix = [];
        if (is_executable('/usr/bin/timeout')) {
            $prefix = ['/usr/bin/timeout', (string) self::TIMEOUT];
        }
        if (is_executable('/usr/bin/nice')) {
            $prefix = array_merge($prefix, ['/usr/bin/nice', '-n', '10']);
        }
        self::$lasterror = '';
        $line = implode(' ', array_map('escapeshellarg', array_merge($prefix, $command))) . ' 2>&1';
        $output = [];
        $code = 0;
        exec($line, $output, $code);
        if ($code !== 0) {
            self::$lasterror = basename($command[0]) . ' failed (' . $code . '): ' .
                    \core_text::substr(implode(' ', array_slice($output, -3)), 0, 500);
            return false;
        }
        return true;
    }

    /**
     * Convert an image with GD, the right way up, at most a given size.
     *
     * @param string $input
     * @param string $output its extension says the format: jpg, png or webp.
     * @param string $mimetype of the input.
     * @param int $maxside the longest side, in pixels, or 0 for any size.
     * @param int $quality of a JPEG or WebP image, 0 to 100.
     * @return bool whether it worked.
     */
    protected static function convert_image(string $input, string $output, string $mimetype, int $maxside, int $quality): bool {
        if (!function_exists('imagecreatefromstring')) {
            return false;
        }
        $size = @getimagesize($input);
        if (!$size || $size[0] * $size[1] > self::IMAGE_MAX_PIXELS) {
            return false;
        }
        raise_memory_limit(MEMORY_HUGE);
        $image = @imagecreatefromstring(file_get_contents($input));
        if (!$image) {
            return false;
        }

        // Phones save photos sideways, and say how to turn them in the EXIF data,
        // which an image made by GD does not have.
        if ($mimetype === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($input);
            $angle = [3 => 180, 6 => 270, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
            if ($angle && ($rotated = imagerotate($image, $angle, 0))) {
                imagedestroy($image);
                $image = $rotated;
            }
        }

        $extension = pathinfo($output, PATHINFO_EXTENSION);
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = ($maxside > 0) ? min(1, $maxside / max($width, $height)) : 1;
        $newwidth = max(1, (int) round($width * $scale));
        $newheight = max(1, (int) round($height * $scale));
        $canvas = imagecreatetruecolor($newwidth, $newheight);
        if ($extension === 'jpg') {
            // A white background, for images with transparent parts.
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        } else {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        }
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
        imagedestroy($image);
        $quality = max(0, min(100, $quality));
        if ($extension === 'png') {
            $done = imagepng($canvas, $output, 9);
        } else if ($extension === 'webp' && function_exists('imagewebp')) {
            $done = imagewebp($canvas, $output, $quality);
        } else if ($extension === 'bmp' && function_exists('imagebmp')) {
            $done = imagebmp($canvas, $output);
        } else {
            $done = ($extension === 'jpg') ? imagejpeg($canvas, $output, $quality) : false;
        }
        imagedestroy($canvas);
        return $done;
    }
}
