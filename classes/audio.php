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
 * Work out what kind of audio a provider sent back.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class audio {
    /** Sample rate of raw PCM audio, if the provider does not say (Gemini uses 24 kHz). */
    const DEFAULT_PCM_RATE = 24000;

    /**
     * Work out what kind of audio this is, from its first bytes. Raw PCM
     * samples (audio/L16 or audio/pcm) are given a WAV header.
     *
     * @param string $audio
     * @param string $mimetype the MIME type the provider gave, if any.
     * @return array [string $audio, string $extension, string $mimetype]
     * @throws \moodle_exception if this is not audio.
     */
    public static function identify(string $audio, string $mimetype = ''): array {
        $start = substr($audio, 0, 12);
        if (
            strncmp($start, 'ID3', 3) === 0 || (strlen($start) > 1 && ord($start[0]) === 0xFF
                && (ord($start[1]) & 0xE0) === 0xE0)
        ) {
            return [$audio, 'mp3', 'audio/mpeg'];
        }
        if (strncmp($start, 'RIFF', 4) === 0 && substr($start, 8, 4) === 'WAVE') {
            return [$audio, 'wav', 'audio/wav'];
        }
        if (strncmp($start, 'OggS', 4) === 0) {
            return [$audio, 'ogg', 'audio/ogg'];
        }
        if (strncmp($start, 'fLaC', 4) === 0) {
            return [$audio, 'flac', 'audio/flac'];
        }
        if (substr($start, 4, 4) === 'ftyp') {
            return [$audio, 'm4a', 'audio/mp4'];
        }
        if (strncmp($start, "\x1A\x45\xDF\xA3", 4) === 0) {
            return [$audio, 'webm', 'audio/webm'];
        }
        if (preg_match('#^audio/(L16|pcm)\b#i', $mimetype)) {
            $rate = preg_match('/rate=(\d+)/i', $mimetype, $matches) ? (int) $matches[1] : self::DEFAULT_PCM_RATE;
            return [self::pcm_to_wav($audio, $rate), 'wav', 'audio/wav'];
        }
        // The text of a JSON or HTML reply, e.g. an error page, is not audio.
        throw new \moodle_exception('errornotaudio', 'tool_aibridge');
    }

    /**
     * Find the MIME type next to the audio data in a JSON reply, e.g.
     * Gemini's "inlineData": {"mimeType": "audio/L16;codec=pcm;rate=24000", "data": "..."}.
     *
     * @param array $decoded the decoded JSON reply.
     * @param string $path the return path of the audio data.
     * @return string the MIME type, or '' if there is none.
     */
    public static function find_mimetype(array $decoded, string $path): string {
        $keys = array_map('trim', explode(',', $path));
        array_pop($keys);
        $parent = $keys ? caller::extract_by_path($decoded, implode(',', $keys)) : $decoded;
        if (is_array($parent)) {
            foreach (['mimeType', 'mime_type', 'mimetype', 'contentType', 'content_type'] as $key) {
                if (isset($parent[$key]) && is_string($parent[$key])) {
                    return $parent[$key];
                }
            }
        }
        return '';
    }

    /**
     * Add a WAV header to raw 16-bit little-endian mono PCM samples.
     *
     * @param string $pcm
     * @param int $rate samples per second.
     * @return string the WAV file.
     */
    public static function pcm_to_wav(string $pcm, int $rate): string {
        $channels = 1;
        $bits = 16;
        $blockalign = $channels * $bits / 8;
        $length = strlen($pcm);
        return 'RIFF' . pack('V', 36 + $length) . 'WAVE' .
                'fmt ' . pack('VvvVVvv', 16, 1, $channels, $rate, $rate * $blockalign, $blockalign, $bits) .
                'data' . pack('V', $length) . $pcm;
    }
}
