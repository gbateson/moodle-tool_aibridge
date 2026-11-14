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

* **One API** for text, speech, images and conversations, and for more kinds
  of request later.
* **Moodle's AI first**, when it is set up, so that sites can use the
  providers and settings they already have.
* **Any provider with a JSON API**, set up with a URL, headers and a request
  template. Presets fill these in for ChatGPT, Claude, Gemini (also for
  large files), DeepSeek, Ollama, OpenAI speech, Gemini speech, ElevenLabs,
  OpenAI images, DALL-E 3 and Imagen.
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

Teachers can find "AI providers", "AI prompt library" and "AI log" in the
course's "More" menu.

## Capabilities

| Capability | Default | Allows |
|---|---|---|
| tool/aibridge:manageproviders | Manager | Manage all providers, and share providers with the whole site |
| tool/aibridge:addprovider | Editing teacher, Manager | Add providers with one's own API keys, and share them in a course or category |
| tool/aibridge:useprovider | Authenticated user | Use the providers shared where the request is made |
| tool/aibridge:viewlog | Editing teacher, Manager | See the AI log |
| tool/aibridge:managetemplates | Manager | Manage all templates in the prompt library |
| tool/aibridge:addtemplate | Editing teacher, Manager | Add templates to the library, and share them in a course or category |
| tool/aibridge:usetemplate | Authenticated user | Use the templates shared where they are used |

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

Images, with the same options as Moodle's own AI subsystem:

```php
$action = new \tool_aibridge\action\generate_image($context->id, $USER->id,
        'A photorealistic image of a swan by a pond.', 'landscape', 'standard');
$response = \tool_aibridge\manager::process($action);
if ($response->get_success()) {
    $file = $response->save_image([...]);
}
```

Conversations, with a different voice for each speaker:

```php
$script = "A=woman (customer calling with a complaint)
B=man (staff on a customer help line)
A: Hello. I'm calling about a coffee machine.
B: Oh, I'm sorry to hear that.";
$response = \tool_aibridge\dialogue::generate($context->id, $USER->id, $script, ['language' => 'en-US']);
```

Each line is spoken by the same speech provider, in a voice chosen by
gender from the provider's `VOICES_FEMALE` and `VOICES_MALE` lists (or given
in the `voices` option), and the pieces are joined, with a short pause
between the lines if the provider sends WAV. Each line is logged.

`\tool_aibridge\media::generate()` makes the media for a marker in a reply,
such as `[[IMAGE ...]]`, `[[AUDIO ...]]`, or `[[AUDIO ...]]` with a
conversation in it.

Other useful methods:

* `manager::is_available($action)`: can any route do this action here?
* `manager::process($action, $providerid)`: use one provider.
* `$action->set_token('MAXTOKENS', '2000')`: give a value to a token in the
  provider's request template.
* `generate_text` takes files (`stored_file` objects) as its fifth argument,
  for providers that take text and files.
* `$response->is_temporary()`: might the request work if it is sent again
  later? This is so for no connection, a busy provider (HTTP 408, 429 and
  5xx), a reply that cannot be read, and rate limits. Background tasks can
  use it to decide whether to try again.
* `provider::get_shared($context, $capabilities)`: the providers shared in a
  context and the contexts above it, but not personal providers. A plugin
  can offer these when teachers choose a provider for students' requests,
  e.g. when responses to a question are assessed.

### Background tasks

Requests are made as a user, e.g. the student whose response is assessed.
The user must have accepted the AI policy (`policy::has_accepted()`), so
show users a link to the policy (`policy::get_url()`) before their data is
sent in a background task.

### Moving a plugin's own AI settings to the bridge

Moodle upgrades admin tools after many other kinds of plugin, so a plugin
upgraded in the same run as the bridge is installed cannot use the
bridge's tables in its upgrade steps. When the bridge is installed, it
calls `{component}_aibridge_installed()` in each plugin's `lib.php`, so the
plugin can finish the move then. SpeakUp (`qtype_speakup`) does this.

In PHPUnit tests, add a provider with the bridge's generator, and mock its
reply:

```php
$this->getDataGenerator()->get_plugin_generator('tool_aibridge')->create_provider();
set_config('requirepolicy', 0, 'tool_aibridge');
\curl::mock_response('{"choices": [{"message": {"content": "Hello"}}]}');
```

## The AI prompt library

The library keeps the prompts that plugins send to the AI, and the output
formats that the AI must follow, so that teachers can see them, try them,
adapt them and share them.

* **Prompts** are the text sent to the AI. A prompt can extend another, by
  replacing its `{{block:name}}...{{endblock}}` sections. For example, the
  TOEIC prompts only give their own `description` block, and get the
  teacher's role, the task, the output rules and the format from the base
  prompt, "Generate GIFT/XML questions".
* **Output formats** describe the output: a template (e.g. a GIFT question
  with placeholders such as `correct-answer`), instructions, and examples.
  Each format says how the reply is read: GIFT, embedded answers (Cloze),
  Moodle XML, JSON or plain text. A prompt links each question type to a
  format, e.g. `multichoice = tool_aibridge:gift-multichoice`.
* **Fragments** are text that prompts and formats share with
  `{{include:shortname}}`, such as the output rules, the rules for writing
  image prompts, and the instructions for bilingual feedback.
* **Parameters**, such as `{{vocabulary-item}}` or `{{feedback-language-2}}`,
  are declared with defaults. A parameter can be marked as personal data,
  and users are told when it is sent. `{{output-format}}`,
  `{{question-format}}` and `{{type-of-questions}}` are filled in from the
  linked format and the question type.
* **Versions.** Each change makes a new version, and old versions can be
  restored. Each request records the version of the prompt it used.
* **Shipped templates.** Plugins ship templates in `db/aibridge_templates.php`.
  They cannot be changed, but they can be copied, and the copy changed.
* **Sharing, import and export.** Templates are shared like providers, and can
  be exported and imported as JSON. Templates never contain API keys.

The "Preview" page shows the exact text of a prompt, with any errors and
warnings, and can send it to the AI and check the reply: chatter and
markdown are removed, GIFT is checked with Moodle's GIFT reader, the
questions are counted, and media markers such as `[[AUDIO ...]]` and
`[[IMAGE ...]]` are listed.

The "Preview" page also has a "Make media" box: paste a marker, such as an
`[[IMAGE ...]]` or a conversation, to hear or see what the providers make.

In a plugin:

```php
use tool_aibridge\library;

$rendering = library::render('mod_vocab:toeic-part2', $context, $USER->id,
        ['vocabulary-item' => 'deadline', 'number-of-questions' => 8], 'multichoice');
list($response, $parsed) = library::generate($rendering, $context, $USER->id, 'mod_vocab');
if ($parsed && $parsed->is_valid()) {
    $gift = $parsed->get_text();
    foreach ($parsed->get_media('AUDIO') as $audio) {
        // Make speech for $audio['text'] with generate_speech.
    }
}
```

A plugin ships templates in `db/aibridge_templates.php`:

```php
$templates = [
    'toeic-part2' => [
        'type' => 'prompt',
        'name' => 'TOEIC R&L (Part 2): Question and response',
        'extends' => 'tool_aibridge:generate-questions',
        'body' => '{{block:description}}These questions assess ...{{endblock}}',
        'formatlinks' => ['multichoice' => 'toeic-part2-format'],
    ],
    'toeic-part2-format' => [
        'type' => 'format',
        'name' => 'TOEIC R&L (Part 2): Question and response',
        'qformat' => 'gift',
        'body' => '::question-name::[[AUDIO question-audio]] { ... }',
        'instructions' => '... {{include:tool_aibridge:multilang-feedback}}',
    ],
];
```

and calls `\tool_aibridge\library::sync_component('mod_vocab')` in its
install and upgrade steps (the bridge also syncs every plugin's templates
when it is upgraded).

## Fine-tuning

On the "Fine-tuning" page, teachers can tune the model of one of their own
providers with an OpenAI-style API, from a training file of examples (JSONL,
one example conversation on each line, at least 10). A scheduled task sends
the file to the provider, starts the job and checks it every five minutes.
When the job has finished, a new provider is added, like the first one but
with the tuned model, and shared where the teacher chose; it can then be
used like any other provider. Fine-tuning has a cost at the provider.

## Request templates

A provider's request template is the JSON body of each request, with tokens
that are filled in when the request is sent:

| Token | Used in |
|---|---|
| `{{MODEL}}`, `{{APIKEY}}` | All requests, and the URL and headers |
| `{{PROMPT}}`, `{{SYSTEM}}` | Text. Without `{{SYSTEM}}`, the system instructions come before the prompt |
| `{{FILE_PARTS}}` | Text and files: one part for each file, from the file template |
| `{{FILE_BASE64}}`, `{{FILE_MIMETYPE}}`, `{{FILE_NAME}}` | The file template, or a request with one file |
| `{{FILE_URI}}` | Instead of `{{FILE_BASE64}}`, for providers whose files are uploaded first |
| `{{TEXT}}`, `{{VOICE}}`, `{{LANGUAGE}}` | Speech |
| `{{SPEAKER}}`, `{{GENDER}}`, `{{ROLE}}`, `{{AUDIOFORMAT}}` | Speech, in conversations (`{{AUDIOFORMAT}}` is `wav`) |
| `{{PROMPT}}`, `{{SIZE}}`, `{{RATIO}}`, `{{QUALITY}}`, `{{STYLE}}` | Images. Sizes and ratios come from `SIZE_SQUARE`, `RATIO_LANDSCAPE`, etc. in "Default values" |

Values are JSON-escaped, so put quotes round string tokens (`"{{PROMPT}}"`)
but not round numbers (`{{MAXTOKENS}}`). "Default values" give tokens a value,
e.g. `{"VOICE": "alloy", "MAXTOKENS": "2000"}`.

Files are sent in the request, as base64, or, for large files such as long
recordings and videos, uploaded first ("Files: Uploaded first"), so that
the request refers to them. The "Gemini (large files)" preset uploads them
with Gemini's Files API, sending the API key in a header.

The "Return path" says where the reply is, e.g. `choices,0,message,content`.
For speech and images, leave it empty if the provider sends back the audio
or image itself, or give the path to base64 data, or to a URL of the image,
in a JSON reply.

## Coming next

* Transcription, and assessment of writing and speaking.
* Video.

## Licence

GNU GPL v3 or later. Copyright 2026 Gordon Bateson.
