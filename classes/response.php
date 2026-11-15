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
 * The result of an AI request: generated text or speech, or an error.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response {
    /** The request went to Moodle's own AI subsystem. */
    const ROUTE_CORE = 'core';

    /** The request went to one of the bridge's providers. */
    const ROUTE_EXTERNAL = 'external';

    /** @var bool did the request succeed? */
    protected $success = false;

    /** @var string the error code, e.g. "policynotaccepted", or '' if there is no error. */
    protected $errorcode = '';

    /** @var string the error message, with any API key removed. */
    protected $error = '';

    /** @var int the HTTP status code of a failed request, or 0 if there was none. */
    protected $httpcode = 0;

    /** Error codes for failures that may not happen if the request is sent again later. */
    const TEMPORARY_ERRORS = ['replynotjson', 'replynotext', 'siteratelimit', 'userratelimit', 'providerratelimit'];

    /** @var string the generated text. */
    protected $content = '';

    /** @var string the generated audio. */
    protected $audio = '';

    /** @var string the generated image. */
    protected $image = '';

    /** @var string the MIME type of the audio or image, e.g. "audio/mpeg". */
    protected $mimetype = '';

    /** @var string the file extension of the audio, e.g. "mp3". */
    protected $extension = '';

    /** @var string WebVTT subtitles for the audio, e.g. of a conversation, or ''. */
    protected $captions = '';

    /** @var string the route: core or external. */
    protected $route = '';

    /** @var int the id of the provider, or 0 for Moodle's own AI subsystem. */
    protected $providerid = 0;

    /** @var string the name of the provider. */
    protected $providername = '';

    /** @var string the model that was used. */
    protected $model = '';

    /** @var int|null the number of tokens in the prompt. */
    protected $prompttokens = null;

    /** @var int|null the number of tokens in the reply. */
    protected $completiontokens = null;

    /** @var int the id of the log record. */
    protected $logid = 0;

    /**
     * Create a successful response.
     *
     * @param string $route
     * @param int $providerid
     * @param string $providername
     * @param string $model
     * @return response
     */
    public static function success(string $route, int $providerid, string $providername, string $model = ''): response {
        $response = new response();
        $response->success = true;
        $response->route = $route;
        $response->providerid = $providerid;
        $response->providername = $providername;
        $response->model = $model;
        return $response;
    }

    /**
     * Create a failed response.
     *
     * @param string $errorcode
     * @param string $error the message. If empty, the string for the error code is used.
     * @param string $route
     * @param int $providerid
     * @param string $providername
     * @return response
     */
    public static function failure(
        string $errorcode,
        string $error = '',
        string $route = '',
        int $providerid = 0,
        string $providername = ''
    ): response {
        $response = new response();
        $response->errorcode = $errorcode;
        if ($error === '' && get_string_manager()->string_exists('error' . $errorcode, 'tool_aibridge')) {
            $error = get_string('error' . $errorcode, 'tool_aibridge');
        }
        $response->error = $error;
        $response->route = $route;
        $response->providerid = $providerid;
        $response->providername = $providername;
        return $response;
    }

    /**
     * Create a failed response from an exception. The error code loses its
     * "error" prefix, e.g. "errorreplynotjson" becomes "replynotjson".
     *
     * @param \moodle_exception $e
     * @param string $error the message, with any API key removed.
     * @param string $route
     * @param int $providerid
     * @param string $providername
     * @return response
     */
    public static function from_exception(
        \moodle_exception $e,
        string $error,
        string $route = '',
        int $providerid = 0,
        string $providername = ''
    ): response {
        $errorcode = preg_replace('/^error(?=[a-z])/', '', $e->errorcode);
        $response = self::failure($errorcode, $error, $route, $providerid, $providername);
        if ($e instanceof request_exception) {
            $response->httpcode = $e->httpcode;
        }
        return $response;
    }

    /**
     * Set the HTTP status code of a failed request.
     *
     * @param int $httpcode
     * @return response
     */
    public function set_httpcode(int $httpcode): response {
        $this->httpcode = $httpcode;
        return $this;
    }

    /**
     * Set the generated text.
     *
     * @param string $content
     * @return response
     */
    public function set_content(string $content): response {
        $this->content = $content;
        return $this;
    }

    /**
     * Set the generated audio.
     *
     * @param string $audio
     * @param string $mimetype
     * @param string $extension
     * @return response
     */
    public function set_audio(string $audio, string $mimetype, string $extension): response {
        $this->audio = $audio;
        $this->mimetype = $mimetype;
        $this->extension = $extension;
        return $this;
    }

    /**
     * Set the subtitles for the audio.
     *
     * @param string $captions WebVTT.
     * @return response
     */
    public function set_captions(string $captions): response {
        $this->captions = $captions;
        return $this;
    }

    /**
     * Set the generated image.
     *
     * @param string $image
     * @param string $mimetype
     * @param string $extension
     * @return response
     */
    public function set_image(string $image, string $mimetype, string $extension): response {
        $this->image = $image;
        $this->mimetype = $mimetype;
        $this->extension = $extension;
        return $this;
    }

    /**
     * Set the token usage.
     *
     * @param int|null $prompttokens
     * @param int|null $completiontokens
     * @return response
     */
    public function set_token_usage(?int $prompttokens, ?int $completiontokens): response {
        $this->prompttokens = $prompttokens;
        $this->completiontokens = $completiontokens;
        return $this;
    }

    /**
     * Set the model that was used.
     *
     * @param string $model
     * @return response
     */
    public function set_model(string $model): response {
        $this->model = $model;
        return $this;
    }

    /**
     * Set the id of the log record.
     *
     * @param int $logid
     * @return response
     */
    public function set_logid(int $logid): response {
        $this->logid = $logid;
        return $this;
    }

    /**
     * Did the request succeed?
     *
     * @return bool
     */
    public function get_success(): bool {
        return $this->success;
    }

    /**
     * Get the error code.
     *
     * @return string
     */
    public function get_errorcode(): string {
        return $this->errorcode;
    }

    /**
     * Get the error message.
     *
     * @return string
     */
    public function get_error(): string {
        return $this->error;
    }

    /**
     * Get the HTTP status code of a failed request.
     *
     * @return int the code, or 0 if the request was not sent, or got no reply.
     */
    public function get_httpcode(): int {
        return $this->httpcode;
    }

    /**
     * Might the request succeed if it is sent again later? This is so for no
     * connection, a timeout, a busy provider (HTTP 408, 429 and 5xx), a reply
     * that cannot be read, and rate limits. Background tasks can use this to
     * decide whether to try again.
     *
     * @return bool
     */
    public function is_temporary(): bool {
        if ($this->success) {
            return false;
        }
        if ($this->errorcode === 'request' || $this->errorcode === 'core') {
            $code = $this->httpcode;
            return ($code === 0 || $code === 408 || $code === 429 || $code >= 500);
        }
        return in_array($this->errorcode, self::TEMPORARY_ERRORS);
    }

    /**
     * Get the generated text.
     *
     * @return string
     */
    public function get_content(): string {
        return $this->content;
    }

    /**
     * Get the generated audio.
     *
     * @return string
     */
    public function get_audio(): string {
        return $this->audio;
    }

    /**
     * Convert the audio or image, e.g. to make it smaller, as the site's settings
     * say (see media_converter::get_options()), or with other options. The new
     * media is only kept if it is no bigger than the old.
     *
     * @param array $options see media_converter::convert_path().
     * @return bool whether the media was changed.
     */
    public function convert_media(array $options = []): bool {
        $isaudio = ($this->audio !== '');
        $content = $isaudio ? $this->audio : $this->image;
        if (!$this->success || $content === '') {
            return false;
        }
        $result = media_converter::convert_content($content, $this->extension, $this->mimetype, $options + ['minsaving' => 1]);
        if (!$result) {
            return false;
        }
        [$content, $extension, $mimetype] = $result;
        if ($isaudio) {
            $this->set_audio($content, $mimetype, $extension);
        } else {
            $this->set_image($content, $mimetype, $extension);
        }
        return true;
    }

    /**
     * Get the subtitles for the audio.
     *
     * @return string WebVTT, or '' if there are none.
     */
    public function get_captions(): string {
        return $this->captions;
    }

    /**
     * Get the generated image.
     *
     * @return string
     */
    public function get_image(): string {
        return $this->image;
    }

    /**
     * Get the MIME type of the generated audio or image.
     *
     * @return string
     */
    public function get_mimetype(): string {
        return $this->mimetype;
    }

    /**
     * Get the file extension of the generated audio or image.
     *
     * @return string
     */
    public function get_extension(): string {
        return $this->extension;
    }

    /**
     * Get the route: core or external.
     *
     * @return string
     */
    public function get_route(): string {
        return $this->route;
    }

    /**
     * Get the id of the provider, or 0 for Moodle's own AI subsystem.
     *
     * @return int
     */
    public function get_providerid(): int {
        return $this->providerid;
    }

    /**
     * Get the name of the provider.
     *
     * @return string
     */
    public function get_providername(): string {
        return $this->providername;
    }

    /**
     * Get the model that was used.
     *
     * @return string
     */
    public function get_model(): string {
        return $this->model;
    }

    /**
     * Get the number of tokens in the prompt.
     *
     * @return int|null
     */
    public function get_prompttokens(): ?int {
        return $this->prompttokens;
    }

    /**
     * Get the number of tokens in the reply.
     *
     * @return int|null
     */
    public function get_completiontokens(): ?int {
        return $this->completiontokens;
    }

    /**
     * Get the id of the log record.
     *
     * @return int
     */
    public function get_logid(): int {
        return $this->logid;
    }

    /**
     * Get a label for content made by this response, so that people know it was made by AI.
     *
     * @return string
     */
    public function get_label(): string {
        return get_string('generatedby', 'tool_aibridge', $this->providername);
    }

    /**
     * Save the generated audio as a file.
     *
     * @param array|\stdClass $filerecord contextid, component, filearea, itemid, filepath, and
     *      optionally filename. The filename's extension is set to match the audio.
     * @return \stored_file
     */
    public function save_audio($filerecord): \stored_file {
        return $this->save_file($filerecord, $this->audio, 'speech');
    }

    /**
     * Save the generated image as a file.
     *
     * @param array|\stdClass $filerecord contextid, component, filearea, itemid, filepath, and
     *      optionally filename. The filename's extension is set to match the image.
     * @return \stored_file
     */
    public function save_image($filerecord): \stored_file {
        return $this->save_file($filerecord, $this->image, 'image');
    }

    /**
     * Save content as a file.
     *
     * @param array|\stdClass $filerecord
     * @param string $content
     * @param string $defaultname the file name, if the file record has none.
     * @return \stored_file
     */
    protected function save_file($filerecord, string $content, string $defaultname): \stored_file {
        $filerecord = (array) $filerecord;
        $filename = isset($filerecord['filename']) ? $filerecord['filename'] : $defaultname;
        $filerecord['filename'] = preg_replace('/\.[a-z0-9]+$/i', '', $filename) . '.' . $this->extension;
        if (!isset($filerecord['filepath'])) {
            $filerecord['filepath'] = '/';
        }
        return get_file_storage()->create_file_from_string($filerecord, $content);
    }
}
