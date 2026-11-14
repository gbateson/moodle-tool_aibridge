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
 * A fine-tuning job: a training file sent to a provider, to make a tuned model.
 *
 * The provider must have an OpenAI-style API (its URL ends with
 * /chat/completions). The job is done in steps, by a scheduled task:
 *
 * 1. "waiting": the training file is uploaded to the provider's /files.
 * 2. "uploaded": a job is started at /fine_tuning/jobs.
 * 3. "running": the job is checked until it has finished. Then a new
 *    provider is added, like the first one but with the tuned model, and
 *    shared where the tuning job is shared.
 *
 * Training files are JSONL, one example conversation on each line, e.g.
 * {"messages": [{"role": "user", "content": "..."}, {"role": "assistant", "content": "..."}]}
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tuning {
    /** The table of tuning jobs. */
    const TABLE = 'tool_aibridge_tunings';

    /** The file area of training files. */
    const FILEAREA = 'tuningfile';

    /** Waiting to upload the training file. */
    const STATUS_WAITING = 'waiting';

    /** The training file is uploaded; waiting to start the job. */
    const STATUS_UPLOADED = 'uploaded';

    /** The job is running. */
    const STATUS_RUNNING = 'running';

    /** The job succeeded, and the tuned provider was added. */
    const STATUS_SUCCEEDED = 'succeeded';

    /** The job failed, or was cancelled. */
    const STATUS_FAILED = 'failed';

    /** The base models that OpenAI can tune, for each kind of model. */
    const TUNABLE_MODELS = [
        '/^gpt-4\.1-nano\b/' => 'gpt-4.1-nano-2025-04-14',
        '/^gpt-4\.1-mini\b/' => 'gpt-4.1-mini-2025-04-14',
        '/^gpt-4\.1\b/' => 'gpt-4.1-2025-04-14',
        '/^gpt-4o-mini\b/' => 'gpt-4o-mini-2024-07-18',
        '/^gpt-4o\b/' => 'gpt-4o-2024-08-06',
        '/^gpt-3\.5\b/' => 'gpt-3.5-turbo-0125',
    ];

    /** @var \stdClass the record. */
    protected $record;

    /**
     * Create the object for a record.
     *
     * @param \stdClass $record
     */
    public function __construct(\stdClass $record) {
        $this->record = $record;
    }

    /**
     * Get a tuning job.
     *
     * @param int $id
     * @return tuning
     */
    public static function get(int $id): tuning {
        global $DB;
        return new tuning($DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST));
    }

    /**
     * Get the record.
     *
     * @return \stdClass
     */
    public function get_record(): \stdClass {
        return $this->record;
    }

    /**
     * Get the id.
     *
     * @return int
     */
    public function get_id(): int {
        return (int) $this->record->id;
    }

    /**
     * Has the job finished, successfully or not?
     *
     * @return bool
     */
    public function is_finished(): bool {
        return in_array($this->record->status, [self::STATUS_SUCCEEDED, self::STATUS_FAILED]);
    }

    /**
     * Can a provider's model be tuned? It needs an OpenAI-style API.
     *
     * @param provider $provider
     * @return bool
     */
    public static function is_tunable(provider $provider): bool {
        $record = $provider->get_record();
        if (!in_array($record->capability, [provider::CAPABILITY_TEXT, provider::CAPABILITY_MULTIMODAL])) {
            return false;
        }
        return (bool) preg_match('#/chat/completions/?$#', trim((string) $record->url));
    }

    /**
     * Get the base URL of a provider's API, e.g. https://api.openai.com/v1.
     *
     * @param provider $provider
     * @return string
     */
    public static function get_base_url(provider $provider): string {
        return preg_replace('#/chat/completions/?$#', '', trim((string) $provider->get_record()->url));
    }

    /**
     * Get the base model to tune: the one given, or the version of the
     * provider's model that can be tuned.
     *
     * @param string $model
     * @return string
     */
    public static function get_tunable_model(string $model): string {
        $model = trim($model);
        foreach (self::TUNABLE_MODELS as $pattern => $tunable) {
            if (preg_match($pattern, $model) && !preg_match('/-\d{4}-\d{2}-\d{2}$/', $model)) {
                return $tunable;
            }
        }
        return $model;
    }

    /**
     * Add a tuning job, with its training file.
     *
     * @param provider $provider the provider whose model is tuned.
     * @param string $name
     * @param int $contextid where the tuned provider will be shared.
     * @param int $ownerid
     * @param string $filename
     * @param string $content the training file (JSONL).
     * @param string $basemodel the model to tune, or '' for the provider's model.
     * @return tuning
     */
    public static function create(
        provider $provider,
        string $name,
        int $contextid,
        int $ownerid,
        string $filename,
        string $content,
        string $basemodel = ''
    ): tuning {
        global $DB;
        if (!self::is_tunable($provider)) {
            throw new \moodle_exception('errornottunable', 'tool_aibridge', '', $provider->get_name());
        }
        $basemodel = trim($basemodel);
        if ($basemodel === '') {
            $basemodel = self::get_tunable_model((string) $provider->get_record()->model);
        }
        $time = time();
        $record = (object) [
            'name' => trim($name),
            'ownerid' => $ownerid,
            'contextid' => $contextid,
            'providerid' => $provider->get_id(),
            'basemodel' => $basemodel,
            'status' => self::STATUS_WAITING,
            'tunedproviderid' => 0,
            'timecreated' => $time,
            'timemodified' => $time,
        ];
        $record->id = $DB->insert_record(self::TABLE, $record);
        get_file_storage()->create_file_from_string([
            'contextid' => $contextid,
            'component' => 'tool_aibridge',
            'filearea' => self::FILEAREA,
            'itemid' => $record->id,
            'filepath' => '/',
            'filename' => clean_param($filename, PARAM_FILE) ?: 'training.jsonl',
            'userid' => $ownerid,
        ], $content);
        return new tuning($record);
    }

    /**
     * Get the training file.
     *
     * @return \stored_file|null
     */
    public function get_file(): ?\stored_file {
        $files = get_file_storage()->get_area_files(
            $this->record->contextid,
            'tool_aibridge',
            self::FILEAREA,
            $this->record->id,
            'id',
            false
        );
        return $files ? reset($files) : null;
    }

    /**
     * Get the tuning jobs that a user can see in a context: their own, those
     * shared there or above, and all of them for managers on the site's page.
     *
     * @param \context $context
     * @param int $userid
     * @return tuning[]
     */
    public static function get_manageable(\context $context, int $userid): array {
        global $DB;
        $params = ['ownerid' => $userid];
        if (
            $context->contextlevel == CONTEXT_SYSTEM
                && has_capability('tool/aibridge:manageproviders', $context, $userid)
        ) {
            $where = '1 = 1';
        } else {
            [$ctxsql, $ctxparams] = $DB->get_in_or_equal($context->get_parent_context_ids(true), SQL_PARAMS_NAMED, 'ctx');
            $where = "ownerid = :ownerid OR contextid $ctxsql";
            $params = array_merge($params, $ctxparams);
        }
        $tunings = [];
        foreach ($DB->get_records_select(self::TABLE, $where, $params, 'timecreated DESC, id DESC') as $record) {
            $tunings[$record->id] = new tuning($record);
        }
        return $tunings;
    }

    /**
     * Can a user change or delete this tuning job?
     *
     * @param int $userid
     * @return bool
     */
    public function can_edit(int $userid): bool {
        if (has_capability('tool/aibridge:manageproviders', \context_system::instance(), $userid)) {
            return true;
        }
        return ($this->record->ownerid == $userid);
    }

    /**
     * Do the next step of every job that has not finished.
     *
     * @return int the number of jobs that were checked.
     */
    public static function advance_all(): int {
        global $DB;
        [$sql, $params] = $DB->get_in_or_equal([self::STATUS_SUCCEEDED, self::STATUS_FAILED], SQL_PARAMS_NAMED, 'st', false);
        $count = 0;
        foreach ($DB->get_records_select(self::TABLE, "status $sql", $params, 'id') as $record) {
            (new tuning($record))->advance();
            $count++;
        }
        return $count;
    }

    /**
     * Do the next step of this job. Errors that may go away (e.g. no
     * connection) are noted, and the step is tried again later; others end
     * the job.
     *
     * @return string the status afterwards.
     */
    public function advance(): string {
        if ($this->is_finished()) {
            return $this->record->status;
        }
        try {
            $provider = provider::get((int) $this->record->providerid);
        } catch (\dml_missing_record_exception $e) {
            return $this->fail(get_string('errortuningprovider', 'tool_aibridge'));
        }
        try {
            switch ($this->record->status) {
                case self::STATUS_WAITING:
                    $this->upload($provider);
                    break;
                case self::STATUS_UPLOADED:
                    $this->start($provider);
                    break;
                case self::STATUS_RUNNING:
                    $this->check($provider);
                    break;
            }
        } catch (request_exception $e) {
            $error = caller::hide_key($e->getMessage(), $provider->get_apikey());
            $temporary = ($e->httpcode === 0 || $e->httpcode === 408 || $e->httpcode === 429 || $e->httpcode >= 500);
            if (!$temporary) {
                return $this->fail($error);
            }
            $this->update(['error' => $error]);
        } catch (\moodle_exception $e) {
            return $this->fail(caller::hide_key($e->getMessage(), $provider->get_apikey()));
        }
        return $this->record->status;
    }

    /**
     * Upload the training file.
     *
     * @param provider $provider
     */
    protected function upload(provider $provider): void {
        $file = $this->get_file();
        if (!$file) {
            throw new \moodle_exception('errortuningfile', 'tool_aibridge');
        }
        $reply = $this->request($provider, 'post', '/files', ['purpose' => 'fine-tune', 'file' => $file]);
        if (empty($reply['id'])) {
            throw new \moodle_exception('errorreplynotext', 'tool_aibridge', '', $provider->get_name());
        }
        $this->update(['remotefileid' => $reply['id'], 'status' => self::STATUS_UPLOADED, 'error' => null]);
    }

    /**
     * Start the job.
     *
     * @param provider $provider
     */
    protected function start(provider $provider): void {
        $params = ['training_file' => $this->record->remotefileid, 'model' => $this->record->basemodel];
        $suffix = substr(preg_replace('/[^a-z0-9-]+/', '-', strtolower($this->record->name)), 0, 18);
        if (trim($suffix, '-') !== '') {
            $params['suffix'] = trim($suffix, '-');
        }
        $reply = $this->request($provider, 'post', '/fine_tuning/jobs', json_encode($params));
        if (empty($reply['id'])) {
            throw new \moodle_exception('errorreplynotext', 'tool_aibridge', '', $provider->get_name());
        }
        $this->update(['jobid' => $reply['id'], 'status' => self::STATUS_RUNNING, 'error' => null]);
    }

    /**
     * Check the job, and add the tuned provider when it has finished.
     *
     * @param provider $provider
     */
    protected function check(provider $provider): void {
        $reply = $this->request($provider, 'get', '/fine_tuning/jobs/' . rawurlencode($this->record->jobid));
        $status = $reply['status'] ?? '';
        if ($status === 'succeeded' && !empty($reply['fine_tuned_model'])) {
            $tuned = $this->add_provider($provider, $reply['fine_tuned_model']);
            $this->update([
                'tunedmodel' => $reply['fine_tuned_model'],
                'tunedproviderid' => $tuned->get_id(),
                'status' => self::STATUS_SUCCEEDED,
                'error' => null,
            ]);
        } else if (in_array($status, ['failed', 'cancelled', 'succeeded'])) {
            $error = $reply['error']['message'] ?? $status;
            $this->fail(get_string('errortuningjob', 'tool_aibridge', $error));
        }
    }

    /**
     * Add a provider like the tuned one, with the tuned model.
     *
     * @param provider $provider
     * @param string $model
     * @return provider
     */
    protected function add_provider(provider $provider, string $model): provider {
        $record = clone($provider->get_record());
        unset($record->id, $record->apikey, $record->contextdepth);
        $record->name = $provider->get_name() . ' (' . $this->record->name . ')';
        $record->model = $model;
        $record->ownerid = $this->record->ownerid;
        $record->contextid = $this->record->contextid;
        $record->enabled = 1;
        return provider::save($record, $provider->get_apikey());
    }

    /**
     * Send a request to the provider's API, and decode the JSON reply.
     *
     * @param provider $provider
     * @param string $method "get" or "post".
     * @param string $path e.g. "/files".
     * @param array|string $params
     * @return array
     * @throws request_exception
     */
    protected function request(provider $provider, string $method, string $path, $params = []): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $apikey = $provider->get_apikey();
        $url = self::get_base_url($provider) . $path;
        $curl = new \curl();
        $headers = ['Authorization: Bearer ' . $apikey];
        if (is_string($params)) {
            $headers[] = 'Content-Type: application/json';
        }
        $curl->setHeader($headers);
        $options = ['CURLOPT_CONNECTTIMEOUT' => caller::CONNECT_TIMEOUT, 'CURLOPT_TIMEOUT' => caller::REQUEST_TIMEOUT];
        $reply = ($method === 'get') ? $curl->get($url, [], $options) : $curl->post($url, $params, $options);
        if ($curl->get_errno() || (!empty($curl->error) && $reply === $curl->error)) {
            throw new request_exception(caller::hide_key((string) $curl->error, $apikey), 0);
        }
        $info = $curl->get_info();
        $code = is_array($info) ? (int) ($info['http_code'] ?? 0) : 0;
        $decoded = json_decode((string) $reply, true);
        if ($code >= 400) {
            $detail = 'HTTP ' . $code;
            if (isset($decoded['error']['message'])) {
                $detail .= ': ' . $decoded['error']['message'];
            }
            throw new request_exception(caller::hide_key($detail, $apikey), $code);
        }
        if (!is_array($decoded)) {
            throw new \moodle_exception('errorreplynotjson', 'tool_aibridge', '', $provider->get_name());
        }
        return $decoded;
    }

    /**
     * End the job, with an error.
     *
     * @param string $error
     * @return string the status.
     */
    protected function fail(string $error): string {
        $this->update(['status' => self::STATUS_FAILED, 'error' => $error]);
        return self::STATUS_FAILED;
    }

    /**
     * Change some fields.
     *
     * @param array $fields
     */
    protected function update(array $fields): void {
        global $DB;
        foreach ($fields as $name => $value) {
            $this->record->$name = $value;
        }
        $this->record->timemodified = time();
        $DB->update_record(self::TABLE, $this->record);
    }

    /**
     * Delete the job and its training file. The tuned provider, and the
     * files and models at the provider, are kept.
     */
    public function delete(): void {
        global $DB;
        get_file_storage()->delete_area_files($this->record->contextid, 'tool_aibridge', self::FILEAREA, $this->record->id);
        $DB->delete_records(self::TABLE, ['id' => $this->record->id]);
    }
}
