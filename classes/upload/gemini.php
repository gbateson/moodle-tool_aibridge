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

namespace tool_aibridge\upload;

use tool_aibridge\caller;
use tool_aibridge\provider;
use tool_aibridge\request_exception;

/**
 * Uploads a file with Google Gemini's resumable uploads (the Files API).
 *
 * 1. POST to the upload URL, with the file's size and type in headers.
 *    The URL for the file's bytes comes back in the X-Goog-Upload-URL header.
 * 2. POST the bytes to that URL. The reply has the file's "uri" and "state".
 * 3. Video files are "PROCESSING" for a while, and cannot be used until they
 *    are "ACTIVE", so check the file's state every few seconds until then.
 *
 * The API key is sent in the x-goog-api-key header, not in the URL.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gemini extends uploader {
    /** The longest time to wait for Gemini to process a file, in seconds. */
    const MAX_WAIT_SECONDS = 180;

    /** The time between checks on a file's state, in seconds. */
    const CHECK_INTERVAL_SECONDS = 3;

    #[\Override]
    public function upload(\stored_file $file, provider $provider): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $record = $provider->get_record();
        $apikey = $provider->get_apikey();
        $tokens = ['MODEL' => (string) $record->model, 'APIKEY' => $apikey];
        $uploadurl = caller::substitute_plain(trim((string) $record->uploadurl), $tokens);
        if ($uploadurl === '') {
            throw new \moodle_exception('errorproviderconfig', 'tool_aibridge', '', $record->name);
        }

        $content = $file->get_content();
        $numbytes = strlen($content);
        $mimetype = $file->get_mimetype();

        // 1. Start the upload.
        $curl = $this->get_curl($apikey, [
            'Content-Type: application/json',
            'X-Goog-Upload-Protocol: resumable',
            'X-Goog-Upload-Command: start',
            'X-Goog-Upload-Header-Content-Length: ' . $numbytes,
            'X-Goog-Upload-Header-Content-Type: ' . $mimetype,
        ]);
        $reply = $curl->post($uploadurl, json_encode(['file' => ['display_name' => $file->get_filename()]]), $this->get_options());
        $this->check($curl, $reply, $apikey);
        $headers = $curl->getResponse();
        $sessionurl = is_array($headers) ? ($headers['x-goog-upload-url'] ?? '') : '';
        if ($sessionurl === '') {
            throw new \moodle_exception('erroruploadfailed', 'tool_aibridge', '', 'no upload URL');
        }

        // 2. Send the bytes.
        $curl = $this->get_curl($apikey, [
            'Content-Length: ' . $numbytes,
            'X-Goog-Upload-Offset: 0',
            'X-Goog-Upload-Command: upload, finalize',
        ]);
        $reply = $curl->post($sessionurl, $content, $this->get_options());
        $this->check($curl, $reply, $apikey);
        $decoded = json_decode((string) $reply, true);
        if (empty($decoded['file']['uri'])) {
            throw new \moodle_exception('erroruploadfailed', 'tool_aibridge', '', 'no file URI');
        }
        $uri = $decoded['file']['uri'];
        $state = $decoded['file']['state'] ?? 'PROCESSING';

        // 3. Wait until the file can be used.
        $timeout = time() + self::MAX_WAIT_SECONDS;
        while ($state === 'PROCESSING') {
            if (time() >= $timeout) {
                throw new request_exception('Gemini was still processing the file after ' . self::MAX_WAIT_SECONDS . ' seconds');
            }
            sleep(self::CHECK_INTERVAL_SECONDS);
            $curl = $this->get_curl($apikey, []);
            $reply = $curl->get($uri, [], $this->get_options());
            $this->check($curl, $reply, $apikey);
            $decoded = json_decode((string) $reply, true);
            $state = $decoded['state'] ?? '';
        }
        if ($state !== 'ACTIVE') {
            throw new \moodle_exception('erroruploadfailed', 'tool_aibridge', '', 'state ' . ($state === '' ? 'unknown' : $state));
        }

        return ['FILE_URI' => $uri, 'FILE_MIMETYPE' => $mimetype, 'FILE_NAME' => $file->get_filename()];
    }

    /**
     * Get a curl object, with the API key and other headers.
     *
     * @param string $apikey
     * @param string[] $headers
     * @return \curl
     */
    protected function get_curl(string $apikey, array $headers): \curl {
        $curl = new \curl();
        if ($apikey !== '') {
            $headers[] = 'x-goog-api-key: ' . $apikey;
        }
        if ($headers) {
            $curl->setHeader($headers);
        }
        return $curl;
    }

    /**
     * Get the curl options.
     *
     * @return array
     */
    protected function get_options(): array {
        return [
            'CURLOPT_CONNECTTIMEOUT' => caller::CONNECT_TIMEOUT,
            'CURLOPT_TIMEOUT' => caller::REQUEST_TIMEOUT,
        ];
    }

    /**
     * Throw an exception if a request was not sent, or failed.
     *
     * @param \curl $curl
     * @param mixed $reply
     * @param string $apikey
     * @throws request_exception
     */
    protected function check(\curl $curl, $reply, string $apikey): void {
        if ($curl->get_errno() || (!empty($curl->error) && $reply === $curl->error)) {
            throw new request_exception(caller::hide_key('Gemini upload: ' . $curl->error, $apikey), 0);
        }
        $info = $curl->get_info();
        $code = is_array($info) ? (int) ($info['http_code'] ?? 0) : 0;
        if ($code >= 400) {
            throw new request_exception(caller::hide_key('Gemini upload: HTTP ' . $code, $apikey), $code);
        }
    }
}
