# AI bridge (tool_aibridge)

The AI bridge lets Moodle plugins use artificial intelligence (AI) through one
simple API. Requests go to Moodle's own AI subsystem where it can do them
(Moodle 4.5 and later), or else to AI providers that the site, or teachers,
set up in the bridge with their own URL, headers and API key.

The bridge works on Moodle 3.11 and later.

## Why a bridge?

Moodle's AI subsystem does a few kinds of request, such as generating text,
and only through the AI provider plugins that Moodle knows. Language-learning
activities need more: speech, assessment of writing and speaking, and soon
video. They also need to run on sites without the AI subsystem.

The bridge gives plugins:

* **One API** for text and speech now, and for more kinds of request later.
* **Moodle's AI first**, when it is set up, so that sites can use the
  providers and settings they already have.
* **Any provider with a JSON API**, set up with a URL, headers and a request
  template. Presets fill these in for ChatGPT, Claude, Gemini, DeepSeek,
  Ollama, OpenAI speech, Gemini speech and ElevenLabs.
* **Teachers' own providers.** Teachers can add providers with their own
  API keys, and share them in their courses or categories, for as long as
  they choose.

## Safeguards

* **Encrypted keys.** API keys are encrypted when they are saved. They are
  never shown again, never sent to the browser, never exported, and are
  removed from error messages.
* **AI policy.** Users accept the AI policy before their requests are sent.
  The policy page also tells them where their data goes: the name of each
  provider they can use, and the host it sends data to.
* **Log.** Each request is logged, with the user, the place, the plugin, the
  provider, the model and the token usage. Prompts and replies are only kept
  if the site says so, and are removed after a set time. Teachers can see the
  log for their courses.
* **Rate limits** for the whole site, for each user, and for each user of a
  shared provider, so that one user cannot use up a teacher's API key.
* **Switches.** The site can turn the bridge off, use only Moodle's own AI,
  or stop teachers from sharing providers.
* **Privacy API.** The log, policy acceptances and users' providers can be
  exported and deleted. API keys are never exported.
* **Labels.** Each reply says which provider made it, so that plugins can
  label AI-generated content.

Requests use Moodle's own HTTP client, so they are subject to the site's
blocked hosts and ports (Site administration > General > Security > HTTP
security). Where possible, put API keys in the headers rather than the URL,
because a blocked URL is reported in Moodle's logs.

## Setting up

1. Install the plugin in `admin/tool/aibridge`.
2. Go to Site administration > AI > AI bridge (or Site administration >
   Plugins > Admin tools > AI bridge, before Moodle 4.5), and check the
   settings.
3. On the "AI providers" page, click "Add an AI provider", choose a preset,
   click "Use these settings", and add your API key.
4. Click the "Test" icon to check the provider works.

Teachers can find "AI providers" and "AI log" in the course's "More" menu.

## Capabilities

| Capability | Default | Allows |
|---|---|---|
| tool/aibridge:manageproviders | Manager | Manage all providers, and share providers with the whole site |
| tool/aibridge:addprovider | Editing teacher, Manager | Add providers with one's own API keys, and share them in a course or category |
| tool/aibridge:useprovider | Authenticated user | Use the providers shared where the request is made |
| tool/aibridge:viewlog | Editing teacher, Manager | See the AI log |

## Using the bridge in a plugin

```php
use tool_aibridge\action\generate_text;
use tool_aibridge\manager;
use tool_aibridge\policy;

$action = new generate_text($context->id, $USER->id, $prompt, 'You are a teacher of English.');
$action->set_purpose('mod_vocab', 'questions');
$response = manager::process($action);

if ($response->get_success()) {
    $text = $response->get_content();
    $label = $response->get_label(); // E.g. "Made by AI (ChatGPT)".
} else if ($response->get_errorcode() == 'policynotaccepted') {
    redirect(policy::get_url($context, $PAGE->url));
} else {
    echo $OUTPUT->notification($response->get_error());
}
```

Speech:

```php
$action = new \tool_aibridge\action\generate_speech($context->id, $USER->id, $text, $voice, 'en-GB');
$response = \tool_aibridge\manager::process($action);
if ($response->get_success()) {
    $file = $response->save_audio([
        'contextid' => $context->id,
        'component' => 'mod_vocab',
        'filearea' => 'audio',
        'itemid' => $itemid,
        'filename' => 'word',
    ]);
}
```

Other useful methods:

* `manager::is_available($action)`: can any route do this action here?
* `manager::process($action, $providerid)`: use one provider.
* `$action->set_token('MAXTOKENS', '2000')`: give a value to a token in the
  provider's request template.
* `generate_text` takes files (`stored_file` objects) as its fifth argument,
  for providers that take text and files.

In PHPUnit tests, add a provider with the bridge's generator, and mock its
reply:

```php
$this->getDataGenerator()->get_plugin_generator('tool_aibridge')->create_provider();
set_config('requirepolicy', 0, 'tool_aibridge');
\curl::mock_response('{"choices": [{"message": {"content": "Hello"}}]}');
```

## Request templates

A provider's request template is the JSON body of each request, with tokens
that are filled in when the request is sent:

| Token | Used in |
|---|---|
| `{{MODEL}}`, `{{APIKEY}}` | All requests, and the URL and headers |
| `{{PROMPT}}`, `{{SYSTEM}}` | Text. Without `{{SYSTEM}}`, the system instructions come before the prompt |
| `{{FILE_PARTS}}` | Text and files: one part for each file, from the file template |
| `{{FILE_BASE64}}`, `{{FILE_MIMETYPE}}`, `{{FILE_NAME}}` | The file template, or a request with one file |
| `{{TEXT}}`, `{{VOICE}}`, `{{LANGUAGE}}` | Speech |

Values are JSON-escaped, so put quotes round string tokens (`"{{PROMPT}}"`)
but not round numbers (`{{MAXTOKENS}}`). "Default values" give tokens a value,
e.g. `{"VOICE": "alloy", "MAXTOKENS": "2000"}`.

The "Return path" says where the reply is, e.g. `choices,0,message,content`.
For speech, leave it empty if the provider sends back the audio itself, or
give the path to base64 audio in a JSON reply.

## Coming next

* A shared library of prompts and output formats (such as GIFT, Moodle XML
  and JSON), which plugins and teachers can adapt and extend.
* Images, transcription, and assessment of writing and speaking.
* Video.

## Licence

GNU GPL v3 or later. Copyright 2026 Gordon Bateson.
