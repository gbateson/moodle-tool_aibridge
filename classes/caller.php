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
 * Send requests to external AI providers.
 *
 * A provider is described by a URL, headers and a JSON request template,
 * with {{TOKEN}} placeholders. Tokens are inserted as plain text in the URL
 * and headers, and JSON-escaped in the request body.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class caller {
    /** How long to wait for a connection, in seconds. */
    const CONNECT_TIMEOUT = 20;

    /** How long to wait for a reply, in seconds. */
    const REQUEST_TIMEOUT = 300;

    /** The file tokens, which are empty when there is no file. */
    const FILE_TOKENS = ['FILE_BASE64', 'FILE_MIMETYPE', 'FILE_URI', 'FILE_NAME'];

    /**
     * Send a request to an external provider.
     *
     * @param provider $provider
     * @param array $tokens token name => value. These are added to the
     *      provider's default token values, and to MODEL and APIKEY.
     * @param \stored_file[] $files files to send, for a multimodal provider.
     * @param array|null $info set to the curl info, e.g. content_type.
     * @return string the raw response body.
     * @throws \moodle_exception
     */
    public static function send(provider $provider, array $tokens, array $files = [], ?array &$info = null): string {
        $record = $provider->get_record();
        if (empty($record->url) || empty($record->requesttemplate)) {
            throw new \moodle_exception('errorproviderconfig', 'tool_aibridge', '', $record->name);
        }
        $apikey = $provider->get_apikey();
        $tokens = array_merge(
            $provider->get_token_defaults(),
            $tokens,
            ['MODEL' => (string) $record->model, 'APIKEY' => $apikey]
        );

        $template = $record->requesttemplate;
        $fileparts = '';
        if ($files) {
            if ($record->capability !== provider::CAPABILITY_MULTIMODAL) {
                throw new \moodle_exception('errornotmultimodal', 'tool_aibridge', '', $record->name);
            }
            $fileparts = self::get_file_parts($provider, $template, $files, $tokens);
        }
        foreach (self::FILE_TOKENS as $name) {
            if (!isset($tokens[$name])) {
                $tokens[$name] = '';
            }
        }

        // Every token in the provider's templates must have a value, or the
        // provider would get the placeholder itself, e.g. a voice called "{{VOICE}}".
        $missing = self::find_missing_tokens([$record->url, (string) $record->headers, $template], $tokens);
        if ($missing) {
            throw new \moodle_exception('errormissingtokens', 'tool_aibridge', '', (object) [
                'provider' => $record->name,
                'tokens' => implode(', ', $missing),
            ]);
        }

        // The URL and headers are not JSON, so tokens go in as they are.
        $url = self::substitute_plain($record->url, $tokens);
        $headers = self::substitute_plain((string) $record->headers, $tokens);

        // The body is JSON, so values are escaped. The file parts are JSON already.
        $body = self::substitute_escaped($template, $tokens);
        $body = self::substitute_plain($body, ['FILE_PARTS' => $fileparts]);
        json_decode($body);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \moodle_exception('errorrequestnotjson', 'tool_aibridge', '', $record->name);
        }
        return self::post($url, $headers, $apikey, $body, $info);
    }

    /**
     * Build the file parts of a request, or add one file's tokens to the tokens.
     *
     * @param provider $provider
     * @param string $template the request template.
     * @param \stored_file[] $files
     * @param array $tokens the tokens, to which a single file's tokens are added.
     * @return string the file parts, each followed by a comma.
     * @throws \moodle_exception
     */
    protected static function get_file_parts(provider $provider, string $template, array $files, array &$tokens): string {
        $record = $provider->get_record();
        $usesparts = (strpos($template, '{{FILE_PARTS}}') !== false);
        if (!$usesparts && strpos($template, '{{FILE_BASE64}}') === false && strpos($template, '{{FILE_URI}}') === false) {
            // Without a slot for the files, some providers quietly ignore them.
            throw new \moodle_exception('errortemplatenofiles', 'tool_aibridge', '', $record->name);
        }
        if (!$usesparts && count($files) > 1) {
            throw new \moodle_exception('errortemplateonefile', 'tool_aibridge', '', $record->name);
        }
        if ($usesparts && trim((string) $record->filetemplate) === '') {
            throw new \moodle_exception('errorfiletemplatemissing', 'tool_aibridge', '', $record->name);
        }
        $parts = '';
        foreach (array_values($files) as $file) {
            $filetokens = self::get_file_tokens($file, $provider);
            if ($usesparts) {
                $part = self::choose_file_part((string) $record->filetemplate, $file->get_mimetype());
                $parts .= self::substitute_escaped($part, $filetokens) . ', ';
            } else {
                $tokens = array_merge($tokens, $filetokens);
            }
        }
        return $parts;
    }

    /**
     * Get the tokens for one file. The file is sent inline as base64 or, if
     * the provider takes uploads, uploaded first and referred to by its URI.
     *
     * @param \stored_file $file
     * @param provider|null $provider
     * @return array
     */
    public static function get_file_tokens(\stored_file $file, ?provider $provider = null): array {
        if ($provider && $provider->get_record()->uploadstyle === provider::UPLOAD_REFERENCE) {
            $uploader = upload\uploader::create((string) $provider->get_record()->uploadprotocol);
            return $uploader->upload($file, $provider) + ['FILE_BASE64' => ''];
        }
        return [
            'FILE_BASE64' => base64_encode($file->get_content()),
            'FILE_MIMETYPE' => $file->get_mimetype(),
            'FILE_NAME' => $file->get_filename(),
            'FILE_URI' => '',
        ];
    }

    /**
     * Choose the file template part for a file's MIME type.
     *
     * @param string $filetemplate one JSON part, or an object of MIME types => parts.
     * @param string $mimetype
     * @return string the JSON part, with its tokens not yet substituted.
     * @throws \moodle_exception
     */
    public static function choose_file_part(string $filetemplate, string $mimetype): string {
        $decoded = json_decode($filetemplate, true);
        if (!is_array($decoded)) {
            throw new \moodle_exception('errorfiletemplatejson', 'tool_aibridge');
        }
        if (!self::is_mimetype_map($decoded)) {
            return trim($filetemplate);
        }
        $mimetype = strtolower($mimetype);
        $candidates = [$mimetype, strtok($mimetype, '/') . '/*', '*'];
        foreach ($candidates as $key) {
            foreach ($decoded as $pattern => $part) {
                if (strtolower(trim($pattern)) === $key) {
                    return json_encode($part, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }
            }
        }
        throw new \moodle_exception('errorfiletype', 'tool_aibridge', '', $mimetype);
    }

    /**
     * Is a decoded file template an object of MIME types => parts, rather than one part?
     *
     * @param array $decoded
     * @return bool
     */
    public static function is_mimetype_map(array $decoded): bool {
        if (!$decoded || array_keys($decoded) === range(0, count($decoded) - 1)) {
            return false;
        }
        foreach ($decoded as $key => $value) {
            if (!is_array($value) || ($key !== '*' && strpos((string) $key, '/') === false)) {
                return false;
            }
        }
        return true;
    }

    /**
     * POST a JSON request body to a provider, and return the raw response.
     *
     * @param string $url
     * @param string $headers extra headers as JSON, with tokens already
     *      substituted. If empty, the API key is sent as a Bearer token.
     * @param string $apikey
     * @param string $body the JSON request body.
     * @param array|null $info set to the curl info, e.g. content_type.
     * @return string the raw response body.
     * @throws request_exception
     */
    public static function post(string $url, string $headers, string $apikey, string $body, ?array &$info = null): string {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $httpheaders = ['Content-Type: application/json'];
        if (trim($headers) !== '') {
            $extra = json_decode($headers, true);
            if (is_array($extra)) {
                foreach ($extra as $key => $value) {
                    $httpheaders[] = "$key: $value";
                }
            }
        } else if ($apikey !== '') {
            // No headers, but there is a key: use the common Bearer-token convention.
            $httpheaders[] = 'Authorization: Bearer ' . $apikey;
        }

        $curl = new \curl();
        $curl->setHeader($httpheaders);
        $response = (string) $curl->post($url, $body, [
            'CURLOPT_CONNECTTIMEOUT' => self::CONNECT_TIMEOUT,
            'CURLOPT_TIMEOUT' => self::REQUEST_TIMEOUT,
        ]);
        $info = $curl->get_info();
        if (!is_array($info)) {
            $info = [];
        }

        // The request could not be sent, e.g. a blocked URL, no connection, or a timeout.
        if ($curl->get_errno() || (!empty($curl->error) && $response === $curl->error)) {
            throw new request_exception(self::hide_key((string) $curl->error, $apikey), 0);
        }

        if (!empty($info['http_code']) && $info['http_code'] >= 400) {
            // Most JSON APIs explain the error in the body.
            $detail = 'HTTP ' . $info['http_code'];
            $error = json_decode($response, true);
            if (is_array($error)) {
                if (isset($error['error']['message'])) {
                    $detail .= ': ' . $error['error']['message'];
                } else if (isset($error['error']) && is_string($error['error'])) {
                    $detail .= ': ' . $error['error'];
                } else if (isset($error['detail']) && is_string($error['detail'])) {
                    $detail .= ': ' . $error['detail'];
                } else if (isset($error['detail']['message'])) {
                    $detail .= ': ' . $error['detail']['message'];
                }
            }
            throw new request_exception(self::hide_key($detail, $apikey), (int) $info['http_code']);
        }
        return $response;
    }

    /**
     * Remove an API key from a message, since messages are logged and shown to teachers.
     *
     * @param string $message
     * @param string $apikey
     * @return string
     */
    public static function hide_key(string $message, string $apikey): string {
        return ($apikey === '') ? $message : str_replace($apikey, '********', $message);
    }

    /**
     * Walk a comma-separated key path into decoded JSON, e.g. "choices,0,message,content".
     *
     * @param mixed $data
     * @param string|null $path
     * @return mixed|null the value found, or null if the path doesn't exist.
     */
    public static function extract_by_path($data, ?string $path) {
        if ($path === null || trim($path) === '') {
            return null;
        }
        $value = $data;
        foreach (explode(',', $path) as $key) {
            $key = trim($key);
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } else {
                return null;
            }
        }
        return $value;
    }

    /**
     * Find the token usage in a decoded JSON reply, from OpenAI, Anthropic or Gemini.
     *
     * @param array $decoded
     * @return array [int|null $prompttokens, int|null $completiontokens]
     */
    public static function find_token_usage(array $decoded): array {
        $paths = [
            ['usage,prompt_tokens', 'usage,completion_tokens'],
            ['usage,input_tokens', 'usage,output_tokens'],
            ['usageMetadata,promptTokenCount', 'usageMetadata,candidatesTokenCount'],
            ['prompt_eval_count', 'eval_count'],
        ];
        foreach ($paths as $path) {
            $prompt = self::extract_by_path($decoded, $path[0]);
            $completion = self::extract_by_path($decoded, $path[1]);
            if (is_numeric($prompt) || is_numeric($completion)) {
                return [is_numeric($prompt) ? (int) $prompt : null, is_numeric($completion) ? (int) $completion : null];
            }
        }
        return [null, null];
    }

    /**
     * Find the tokens in templates that have no value.
     *
     * @param string[] $templates
     * @param array $tokens name => value.
     * @return string[] the names of the tokens that have no value.
     */
    public static function find_missing_tokens(array $templates, array $tokens): array {
        $missing = [];
        foreach ($templates as $template) {
            if (preg_match_all('/\{\{([A-Z0-9_]+)\}\}/', $template, $matches)) {
                foreach ($matches[1] as $name) {
                    if ($name !== 'FILE_PARTS' && !array_key_exists($name, $tokens)) {
                        $missing[$name] = $name;
                    }
                }
            }
        }
        return array_values($missing);
    }

    /**
     * Substitute {{TOKEN}} placeholders as plain text, e.g. in a URL or headers.
     *
     * @param string $template
     * @param array $tokens token name => value.
     * @return string
     */
    public static function substitute_plain(string $template, array $tokens): string {
        $search = [];
        $replace = [];
        foreach ($tokens as $name => $value) {
            $search[] = '{{' . $name . '}}';
            $replace[] = (string) $value;
        }
        return str_replace($search, $replace, $template);
    }

    /**
     * Substitute {{TOKEN}} placeholders in a JSON template. Each value is
     * JSON-escaped, since the template supplies the quotes around it.
     *
     * @param string $template
     * @param array $tokens token name => value.
     * @return string
     */
    public static function substitute_escaped(string $template, array $tokens): string {
        $search = [];
        $replace = [];
        foreach ($tokens as $name => $value) {
            $search[] = '{{' . $name . '}}';
            $json = json_encode((string) $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            $replace[] = substr($json, 1, -1);
        }
        return str_replace($search, $replace, $template);
    }
}
