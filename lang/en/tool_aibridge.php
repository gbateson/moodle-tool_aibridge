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

/**
 * English strings for the AI bridge.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addprovider'] = 'Add an AI provider';
$string['addtemplate_format'] = 'Add an output format';
$string['addtemplate_fragment'] = 'Add a fragment';
$string['addtemplate_prompt'] = 'Add a prompt';
$string['addtuning'] = 'Add a fine-tuning job';
$string['aibridge:addprovider'] = 'Add AI providers with own API keys, and share them';
$string['aibridge:addtemplate'] = 'Add templates to the AI prompt library, and share them';
$string['aibridge:manageproviders'] = 'Manage all AI providers';
$string['aibridge:managetemplates'] = 'Manage all templates in the AI prompt library';
$string['aibridge:useprovider'] = 'Use shared AI providers';
$string['aibridge:usetemplate'] = 'Use shared templates from the AI prompt library';
$string['aibridge:viewlog'] = 'View the AI log';
$string['allowexternal'] = 'Use the bridge\'s own providers';
$string['allowexternal_desc'] = 'If this is not set, requests are only sent to Moodle\'s own AI subsystem, and the providers on the "AI providers" page are not used.';
$string['allowuserproviders'] = 'Teachers can add providers';
$string['allowuserproviders_desc'] = 'If this is set, users with the capability "tool/aibridge:addprovider" can add AI providers with their own API keys, and share them in courses and categories. If this is not set, only the site\'s providers are used, and users\' own providers are only used by the users themselves.';
$string['anysize'] = 'Any size';
$string['apikey'] = 'API key';
$string['apikey_help'] = 'The secret key from the AI provider. The key is encrypted when it is saved, and it is never shown again, exported, or sent to the browser. Put {{APIKEY}} in the URL or headers where the provider expects the key. If there are no headers, the key is sent as a Bearer token.';
$string['apikeykeep'] = 'New API key';
$string['apikeyset'] = 'Set';
$string['applypreset'] = 'Use these settings';
$string['audiobitrate'] = 'Audio bit rate';
$string['audiobitrate_desc'] = 'The bit rate of MP3 audio, in mono. 48 kbit/s is plenty for speech.';
$string['audioformat'] = 'Audio format';
$string['audioformat_desc'] = 'The format of the audio that AI providers make, e.g. speech. Some providers send WAV audio, which is about ten times bigger than MP3. This needs ffmpeg.';
$string['basemodel'] = 'Model to tune';
$string['basemodel_help'] = 'The model to tune, e.g. gpt-4.1-mini-2025-04-14. Leave this empty to use a version of the provider\'s own model that can be tuned. Only some models can be tuned: see the provider\'s documentation.';
$string['bitrate'] = '{$a} kbit/s';
$string['body_format'] = 'Template';
$string['body_format_help'] = 'The template of the output, e.g. a GIFT question with placeholders such as question-text and correct-answer, which the AI replaces with its own text. It can include fragments with {{include:shortname}} and use parameters such as {{target-language}}.';
$string['body_fragment'] = 'Text';
$string['body_fragment_help'] = 'Text that prompts and formats include with {{include:shortname}}. It can use parameters such as {{target-language}}.';
$string['body_prompt'] = 'Prompt';
$string['body_prompt_help'] = 'The text sent to the AI.

* {{name}}: the value of a parameter, e.g. {{vocabulary-item}}.
* {{output-format}}: the format linked to the question type, with its instructions and examples. {{question-format}} is its name, e.g. GIFT, and {{type-of-questions}} is the name of the question type.
* {{include:shortname}}: the text of a fragment or format.
* {{block:name}}...{{endblock}}: a section that prompts which extend this one can replace.

If this prompt extends another, only its blocks are used: each replaces the block of the same name in the parent.';
$string['bridgedisabled'] = 'The AI bridge is turned off, so no requests are sent.';
$string['capability'] = 'Capability';
$string['capability_help'] = 'What the provider does.

* **Text**: makes text from a prompt.
* **Text and files**: makes text from a prompt and files, such as images, audio or PDF files.
* **Speech**: makes speech from text.
* **Image**: makes an image from a prompt.';
$string['capability_image'] = 'Image';
$string['capability_multimodal'] = 'Text and files';
$string['capability_speech'] = 'Speech';
$string['capability_text'] = 'Text';
$string['component'] = 'Plugin';
$string['confirmdelete'] = 'Do you really want to delete the AI provider "{$a}"? Its API key will be deleted too.';
$string['confirmdeletetemplate'] = 'Do you really want to delete the template "{$a}" and all its versions?';
$string['confirmdeletetuning'] = 'Delete the fine-tuning job "{$a}"? Its training file is deleted from this site. The tuned provider, if there is one, is kept, and so are the files and models at the AI provider.';
$string['connection'] = 'Connection';
$string['contentretention'] = 'Keep prompts and replies for';
$string['contentretention_desc'] = 'After this time, prompts and replies are removed from the log. The rest of each log record is kept.';
$string['convertcondition_exec'] = 'PHP can run programs (the exec() function is enabled)';
$string['convertcondition_ffmpeg'] = 'ffmpeg was found, for audio and video files';
$string['convertcondition_gd'] = 'PHP\'s GD library is available, for images';
$string['convertcondition_ghostscript'] = 'Ghostscript was found, for PDF files ("Path to ghostscript", in Site administration > Server > System paths)';
$string['copyof'] = 'Copy of {$a}';
$string['copytoedit'] = 'Copy this template to change it';
$string['coreai'] = 'Moodle AI';
$string['coredatanotice'] = 'Some requests are sent to the AI providers set up for this site: {$a}.';
$string['coreroute'] = 'Moodle\'s own AI subsystem';
$string['coreroute_desc'] = 'In Moodle 4.5 and later, requests to make text can be sent to the AI providers set up in Moodle\'s own AI subsystem. Requests that Moodle cannot do, such as making speech, are always sent to the bridge\'s own providers.';
$string['coreroutefirst'] = 'Use Moodle\'s AI first';
$string['coreroutelast'] = 'Use Moodle\'s AI if no other provider can be used';
$string['coreroutenever'] = 'Never use Moodle\'s AI';
$string['datanotice'] = 'Data notice';
$string['datanotice_help'] = 'What users are told about where their data goes when they use this provider. If this is empty, users are told the name of the provider and the host that data is sent to.';
$string['defaultdatanotice'] = 'Requests to {$a->name} are sent to {$a->host}.';
$string['defaultvoice'] = 'default voice';
$string['editprovider'] = 'Edit an AI provider';
$string['enabled'] = 'Enabled';
$string['enabled_desc'] = 'If this is not set, the AI bridge sends no requests.';
$string['erroraudiomismatch'] = 'The pieces of audio cannot be joined, because they are in different formats.';
$string['errorcore'] = 'Moodle\'s AI subsystem could not do this request.';
$string['errordialogueline'] = 'Line {$a->line} of the conversation could not be spoken: {$a->error}';
$string['errordialoguetoolong'] = 'The conversation is too long. The most is {$a} lines.';
$string['errordisabled'] = 'The AI bridge is turned off.';
$string['errorextendsloop'] = 'The prompt "{$a}" extends itself, or extends too many levels of prompts.';
$string['errorfiletemplatejson'] = 'The file template is not valid JSON.';
$string['errorfiletemplatemissing'] = 'The request template of "{$a}" has {{FILE_PARTS}}, but there is no file template.';
$string['errorfiletype'] = 'The provider does not take files of type {$a}.';
$string['errorformatlinks'] = 'Each line must be "question type = format short name".';
$string['errorgiftbraces'] = 'The reply has a { without a }, or a } without a {.';
$string['errorimagedownload'] = 'The image could not be downloaded from the provider.';
$string['errorimport'] = 'This is not an export of AI prompt library templates.';
$string['errorincludeloop'] = '"{$a}" includes itself, or includes too many levels of templates.';
$string['errorjsonobject'] = 'This must be a JSON object, e.g. {"name": "value"}.';
$string['errorjsontemplate'] = 'This is not valid JSON, even after the {{TOKENS}} are filled in.';
$string['errormediatype'] = 'Media of type "{$a}" cannot be made yet.';
$string['errormissingtokens'] = 'The request to "{$a->provider}" needs a value for {$a->tokens}. Please add it to the provider\'s default token values.';
$string['errornegative'] = 'This cannot be less than 0.';
$string['errornocloze'] = 'The reply has no embedded answers, e.g. {1:MCVS:=right~wrong}.';
$string['errornocore'] = 'Moodle\'s AI subsystem cannot do this request.';
$string['errornodialogue'] = 'The conversation has no lines, e.g. "A: Hello."';
$string['errornoformat'] = 'No output format is linked to the question type "{$a}".';
$string['errornoprompt'] = 'There is no prompt.';
$string['errornoproviders'] = 'No AI provider can do this request here.';
$string['errornoquestions'] = 'The reply has no questions.';
$string['errornotaformat'] = '"{$a}" is not an output format.';
$string['errornotaprompt'] = '"{$a}" is not a prompt.';
$string['errornotaudio'] = 'The provider did not send back audio.';
$string['errornotext'] = 'There is no text to speak.';
$string['errornotimage'] = 'The provider did not send back an image.';
$string['errornotmultimodal'] = 'The provider "{$a}" cannot take files.';
$string['errornottunable'] = 'The model of "{$a}" cannot be tuned here: only providers with an OpenAI-style API (a URL ending in /chat/completions) can be tuned.';
$string['errorparameterrequired'] = 'The parameter "{$a}" needs a value.';
$string['errorparameters'] = 'This must be JSON: a list of parameters, or an object of names and defaults.';
$string['errorpolicynotaccepted'] = 'You must accept the AI policy first.';
$string['errorproviderconfig'] = 'The provider "{$a}" has no URL or no request template.';
$string['errorproviderratelimit'] = 'You have sent the most requests allowed to this provider in one hour. Please try again later.';
$string['errorreplyempty'] = 'The reply is empty.';
$string['errorreplynotext'] = 'The reply from "{$a}" had no text at its return path.';
$string['errorreplynotjson'] = 'The reply from "{$a}" was not JSON.';
$string['errorreplynotjsonformat'] = 'The reply is not valid JSON: {$a}';
$string['errorreplynotxml'] = 'The reply is not valid XML: {$a}';
$string['errorrequest'] = 'The AI provider could not do the request: {$a}';
$string['errorrequestnotjson'] = 'The request to "{$a}" is not valid JSON. Please check the request template.';
$string['errorshareduntil'] = 'This must be after the date the sharing starts.';
$string['errorshortname'] = 'Use only lower-case letters, numbers, hyphens, underscores and full stops.';
$string['errorshortnameused'] = 'This short name is already used.';
$string['errorsiteratelimit'] = 'The site has sent the most AI requests allowed in one hour. Please try again later.';
$string['errortemplatehaschildren'] = '"{$a}" cannot be deleted, because other prompts extend it.';
$string['errortemplateinvalid'] = 'The prompt could not be made.';
$string['errortemplatenofiles'] = 'The request template of "{$a}" has no place for files: add {{FILE_PARTS}}, {{FILE_BASE64}} or {{FILE_URI}}.';
$string['errortemplatenotfound'] = 'The template "{$a}" was not found, or cannot be used here.';
$string['errortemplateonefile'] = 'The request template of "{$a}" can only take one file. Use {{FILE_PARTS}} to send more files.';
$string['errortexttoolong'] = 'The text is too long. The most is {$a} characters.';
$string['errortrainingcount'] = 'The training file needs at least {$a} examples, one on each line.';
$string['errortrainingline'] = 'Line {$a} of the training file is not an example: each line must be JSON with "messages", e.g. {"messages": [{"role": "user", "content": "..."}, {"role": "assistant", "content": "..."}]}';
$string['errortuningfile'] = 'The training file is missing.';
$string['errortuningjob'] = 'The fine-tuning job did not succeed: {$a}';
$string['errortuningprovider'] = 'The provider whose model was being tuned has been deleted.';
$string['erroruploadfailed'] = 'The file could not be uploaded to the AI provider ({$a}).';
$string['erroruploadprotocol'] = 'Unknown way of uploading files: {$a}';
$string['errorurl'] = 'This must be a URL that starts with http:// or https://';
$string['erroruserratelimit'] = 'You have sent the most AI requests allowed in one hour. Please try again later.';
$string['examples'] = 'Examples';
$string['examples_help'] = 'Examples of good output. They are put after the template and instructions.';
$string['export'] = 'Export';
$string['exportall'] = 'Export my templates';
$string['extends'] = 'Extends';
$string['extends_help'] = 'The prompt that this prompt is based on. This prompt\'s blocks replace the blocks of the same name in that prompt, and its format links and parameters are added to that prompt\'s.';
$string['externaldisabled'] = 'The site does not use the bridge\'s own providers, so requests are only sent to Moodle\'s own AI subsystem.';
$string['filetemplate'] = 'File template';
$string['filetemplate_help'] = 'The JSON part for one file, which replaces {{FILE_PARTS}} in the request template, once for each file. Tokens: {{FILE_BASE64}} (or {{FILE_URI}}, if files are uploaded first), {{FILE_MIMETYPE}} and {{FILE_NAME}}.

For different kinds of file, use an object of MIME types, e.g. {"image/*": {...}, "application/pdf": {...}}. "*" matches any file.';
$string['formatkeep'] = 'Keep the format that the provider sends';
$string['formatlinks'] = 'Formats for question types';
$string['formatlinks_help'] = 'The format to use for each question type, one per line, e.g.

    multichoice = tool_aibridge:gift-multichoice
    truefalse = tool_aibridge:gift-truefalse

Use * for any question type.';
$string['generatedby'] = 'Made by AI ({$a})';
$string['headers'] = 'Headers';
$string['headers_help'] = 'The HTTP headers, as a JSON object, e.g. {"x-api-key": "{{APIKEY}}"}. Tokens: {{APIKEY}} and {{MODEL}}. If this is empty, the API key is sent as "Authorization: Bearer ...".';
$string['host'] = 'Data goes to';
$string['imageformat'] = 'Image format';
$string['imageformat_desc'] = 'The format of the images that AI providers make. JPEG files are much smaller than PNG files; PNG keeps transparent parts.';
$string['imagemaxside'] = 'Largest image size';
$string['imagemaxside_desc'] = 'Images that AI providers make are made smaller, if they are wider or taller than this.';
$string['imagequality'] = 'JPEG quality';
$string['imagequality_desc'] = 'The quality of JPEG images, from 0 to 100. Higher quality means bigger files.';
$string['import'] = 'Import';
$string['importfile'] = 'File';
$string['importtext'] = 'Or paste the export here';
$string['importtext_help'] = 'The JSON of an export from an AI prompt library. Each template becomes your own, shared as you choose below. Short names that are already used get a number added.';
$string['instructions'] = 'Instructions';
$string['instructions_help'] = 'What each placeholder in the template means, and any rules for the output. They are put after the template.';
$string['library'] = 'AI prompt library';
$string['libraryintro'] = 'Prompts are sent to the AI. Output formats describe the output that the AI must give, and fragments are text that prompts and formats share. Templates that plugins ship cannot be changed, but you can copy them and change the copy.';
$string['linkedformat'] = 'The format linked to the question type';
$string['log'] = 'AI log';
$string['logprompts'] = 'Keep prompts';
$string['logprompts_desc'] = 'If this is set, the text of each prompt is kept in the log. Prompts may contain personal data, such as students\' writing.';
$string['logresponses'] = 'Keep replies';
$string['logresponses_desc'] = 'If this is set, the text of each reply is kept in the log.';
$string['logretention'] = 'Keep log records for';
$string['logretention_desc'] = 'After this time, log records are deleted.';
$string['makemedia'] = 'Make';
$string['media'] = 'Media to make';
$string['mediafiles'] = 'Media files';
$string['mediafiles_desc'] = 'The images and audio that AI providers make can be converted, to make them smaller, before they are saved. Other plugins, e.g. SpeakUp, also use these tools to make students\' large files smaller. A file is only changed if the result is no bigger. This uses programs on the server:';
$string['mediamade'] = 'Made by {$a}';
$string['mediatext'] = 'Text';
$string['mediatype'] = 'Type';
$string['model'] = 'Model';
$string['model_help'] = 'The name of the AI model, e.g. "gpt-4o". Use {{MODEL}} in the URL or request template where the provider expects it.';
$string['noproviders'] = 'There are no AI providers here yet.';
$string['notemplates'] = 'There are none yet.';
$string['notunableproviders'] = 'To tune a model, first add a provider with an OpenAI-style API (e.g. with the "ChatGPT" preset), with your own API key.';
$string['notunings'] = 'There are no fine-tuning jobs here yet.';
$string['owner'] = 'Added by';
$string['parameters'] = 'Parameters';
$string['parameters_help'] = 'The parameters of this template, as JSON, with their defaults, e.g.

    [{"name": "target-language", "default": "English", "required": true},
     {"name": "student-text", "personal": true}]

or simply {"target-language": "English"}. A parameter marked "personal" holds personal data, such as what a student wrote, and users are told when it is sent.';
$string['pathtoffmpeg'] = 'Path to ffmpeg';
$string['pathtoffmpeg_desc'] = 'ffmpeg converts audio and video files. On Ubuntu and Debian, install it with "sudo apt install ffmpeg"; it is then usually /usr/bin/ffmpeg.';
$string['personaldata'] = '(personal data)';
$string['personaldatawarning'] = 'These parameters hold personal data, which will be sent to the AI provider: {$a}';
$string['pixels'] = '{$a} pixels';
$string['pluginname'] = 'AI bridge';
$string['policy'] = 'AI policy';
$string['policyaccept'] = 'Accept';
$string['policyaccepted'] = 'You have accepted the AI policy.';
$string['policyconfirm'] = 'Do you accept the AI policy?';
$string['policydefault'] = '<p>Some activities on this site use artificial intelligence (AI) to make text, speech and other materials, and to give feedback.</p>
<p>When you use these activities, what you write or say, and any files you send, are sent to an AI provider outside this site. The provider may keep this data for a while, according to its own policy. Do not send personal or sensitive information unless you need to.</p>
<p>AI can make mistakes. Please check what it makes before you use it. Content made by AI is labelled as such.</p>';
$string['policytext'] = 'Policy text';
$string['policytext_desc'] = 'The policy that users accept before their requests are sent to an AI provider. If this is empty, a default policy is shown. Each user is also told where their data goes.';
$string['preset'] = 'Preset';
$string['preset_help'] = 'Ready-made settings for a well-known AI provider. Choose a preset and click "Use these settings", then add your API key. You can change any of the settings.';
$string['preview'] = 'Preview';
$string['previewformat'] = 'Format';
$string['previewformat_help'] = 'The output format. By default, it is the format linked to the question type.';
$string['previewof'] = 'Preview: {$a}';
$string['privacy:metadata:aiprovider'] = 'Prompts and files are sent to AI providers outside Moodle, to make text, speech and other materials.';
$string['privacy:metadata:aiprovider:files'] = 'Files sent with a prompt, such as images or recordings.';
$string['privacy:metadata:aiprovider:prompt'] = 'The text of the prompt, which may include what the user wrote or said.';
$string['privacy:metadata:aiprovider:trainingfile'] = 'Training files for fine-tuning, which teachers upload, are sent to the AI provider.';
$string['privacy:metadata:core_files'] = 'Training files for fine-tuning are stored in Moodle\'s file system.';
$string['privacy:metadata:log'] = 'Each request sent to an AI provider.';
$string['privacy:metadata:log:action'] = 'What was asked for, e.g. text or speech.';
$string['privacy:metadata:log:component'] = 'The plugin that sent the request.';
$string['privacy:metadata:log:contextid'] = 'Where the request was sent from.';
$string['privacy:metadata:log:model'] = 'The AI model.';
$string['privacy:metadata:log:prompt'] = 'The prompt, if the site keeps prompts.';
$string['privacy:metadata:log:providername'] = 'The AI provider.';
$string['privacy:metadata:log:response'] = 'The reply, if the site keeps replies.';
$string['privacy:metadata:log:templateid'] = 'The template that made the prompt.';
$string['privacy:metadata:log:timecreated'] = 'When the request was sent.';
$string['privacy:metadata:log:userid'] = 'The user who sent the request.';
$string['privacy:metadata:policy'] = 'Users who have accepted the AI policy.';
$string['privacy:metadata:policy:contextid'] = 'Where the user accepted the policy.';
$string['privacy:metadata:policy:timeaccepted'] = 'When the user accepted the policy.';
$string['privacy:metadata:policy:userid'] = 'The user who accepted the policy.';
$string['privacy:metadata:providers'] = 'AI providers added by users.';
$string['privacy:metadata:providers:apikey'] = 'The encrypted API key. It is never exported.';
$string['privacy:metadata:providers:name'] = 'The name of the provider.';
$string['privacy:metadata:providers:ownerid'] = 'The user who added the provider.';
$string['privacy:metadata:providers:timecreated'] = 'When the provider was added.';
$string['privacy:metadata:providers:url'] = 'The URL that requests are sent to.';
$string['privacy:metadata:templates'] = 'Templates in the AI prompt library that users added.';
$string['privacy:metadata:templates:body'] = 'The text of the template.';
$string['privacy:metadata:templates:name'] = 'The name of the template.';
$string['privacy:metadata:templates:ownerid'] = 'The user who added the template.';
$string['privacy:metadata:templates:timecreated'] = 'When the template was added.';
$string['privacy:metadata:tplversions'] = 'Each version of a template.';
$string['privacy:metadata:tplversions:timecreated'] = 'When the version was saved.';
$string['privacy:metadata:tplversions:userid'] = 'The user who saved the version.';
$string['privacy:metadata:tunings'] = 'Fine-tuning jobs, which make tuned models from training files.';
$string['privacy:metadata:tunings:basemodel'] = 'The model that is tuned.';
$string['privacy:metadata:tunings:name'] = 'The name of the fine-tuning job.';
$string['privacy:metadata:tunings:ownerid'] = 'The user who added the fine-tuning job.';
$string['privacy:metadata:tunings:timecreated'] = 'When the fine-tuning job was added.';
$string['privacy:metadata:tunings:tunedmodel'] = 'The name of the tuned model.';
$string['provider'] = 'Provider';
$string['providerdeleted'] = 'The AI provider "{$a}" was deleted.';
$string['providers'] = 'AI providers';
$string['providersaved'] = 'The AI provider "{$a}" was saved.';
$string['providersintro'] = 'These AI providers can be used here. Requests go to your own providers first, then to those shared nearest to where the request is made.';
$string['providerwarning'] = 'Requests to this provider are sent outside Moodle, and may contain what users write or say. Only add providers that you trust, and check their data policies. Each request is logged, with the name of the user who sent it.';
$string['qformat'] = 'Output format';
$string['qformat_gift'] = 'GIFT';
$string['qformat_help'] = 'How the reply is read and checked: GIFT and Moodle XML are checked as questions, embedded answers (Cloze) are counted, and JSON is checked as JSON.';
$string['qformat_json'] = 'JSON';
$string['qformat_multianswer'] = 'Embedded answers (Cloze)';
$string['qformat_text'] = 'Plain text';
$string['qformat_xml'] = 'Moodle XML';
$string['questiontype'] = 'Question type';
$string['ratelimits'] = 'Rate limits';
$string['ratelimits_desc'] = 'The most requests that can be sent in one hour. 0 means no limit.';
$string['removekey'] = 'Remove the API key';
$string['renderedprompt'] = 'The prompt that will be sent';
$string['reply'] = 'Reply';
$string['replyformat'] = 'Reply format';
$string['replyvalid'] = 'The reply can be used: {$a} question(s).';
$string['requesttemplate'] = 'Request template';
$string['requesttemplate_help'] = 'The JSON body of each request, with {{TOKENS}} that are filled in when the request is sent. Values are JSON-escaped, so put quotes round a token that is a string, e.g. "{{PROMPT}}", but not round a number, e.g. {{MAXTOKENS}}.

* All requests: {{MODEL}}, {{APIKEY}}, and the tokens in "Default values".
* Text: {{PROMPT}} and {{SYSTEM}}. If there is no {{SYSTEM}}, the system instructions come before the prompt.
* Text and files: {{FILE_PARTS}}, or for one file {{FILE_BASE64}}, {{FILE_MIMETYPE}} and {{FILE_NAME}}.
* Speech: {{TEXT}}, {{VOICE}} and {{LANGUAGE}}; in conversations also {{SPEAKER}}, {{GENDER}} and {{ROLE}}, and {{AUDIOFORMAT}} is "wav".
* Images: {{PROMPT}}, {{SIZE}}, {{RATIO}}, {{QUALITY}} and {{STYLE}}.';
$string['requirepolicy'] = 'Users must accept the AI policy';
$string['requirepolicy_desc'] = 'If this is set, each user must accept the AI policy before their requests are sent.';
$string['restoreversion'] = 'Restore';
$string['result'] = 'Result';
$string['returnpath'] = 'Return path';
$string['returnpath_help'] = 'Where to find the reply in the JSON that the provider sends back, as keys separated by commas, e.g. "choices,0,message,content". For speech, leave this empty if the provider sends back the audio itself, or give the path to the base64 audio.';
$string['routes'] = 'Where requests go';
$string['safeguards'] = 'Safeguards';
$string['sendtoai'] = 'Send to AI';
$string['settings'] = 'Settings';
$string['sharedfrom'] = 'Share from';
$string['shareduntil'] = 'Share until';
$string['shareonlyme'] = 'Only me';
$string['shareonlyowner'] = 'Only the owner';
$string['sharesite'] = 'The whole site';
$string['sharetemplate'] = 'Share with';
$string['sharetemplate_help'] = 'Who can use this template. If it is shared in a course or category, teachers there can use it and copy it, but only you can change it.';
$string['sharewith'] = 'Share with';
$string['sharewith_help'] = 'Who can use this provider. If it is shared in a course or category, everyone there with the capability "tool/aibridge:useprovider" can use it, and their requests use your API key. Each request is logged with the name of the user who sent it.';
$string['sharing'] = 'Sharing';
$string['shippedby'] = '{$a} (shipped)';
$string['shortname'] = 'Short name';
$string['shortname_help'] = 'A unique name, used to include this template in others, e.g. {{include:my-rules}}, and to link prompts to formats. Use lower-case letters, numbers and hyphens. If it is empty, one is made from the name.';
$string['siteratelimit'] = 'Requests for the whole site';
$string['siteratelimit_desc'] = 'The most requests that the whole site can send in one hour.';
$string['siteuserratelimit_desc'] = 'The most requests that one user can send in one hour.';
$string['sortorder'] = 'Sort order';
$string['sortorder_help'] = 'If several providers can do a request, those with a lower number are tried first.';
$string['taskadvancetunings'] = 'Check fine-tuning jobs';
$string['taskcleanuplog'] = 'Delete old AI log records';
$string['templatecopied'] = '"{$a}" was copied. You can now change the copy.';
$string['templatedeleted'] = 'The template "{$a}" was deleted.';
$string['templatesaved'] = 'The template "{$a}" was saved.';
$string['templateshipped'] = 'This template is shipped by {$a}, so it cannot be changed. Copy it to make your own version.';
$string['templatesimported'] = '{$a} template(s) were imported.';
$string['templatesused'] = 'Templates used';
$string['templatetype_format'] = 'Output format';
$string['templatetype_fragment'] = 'Fragment';
$string['templatetype_prompt'] = 'Prompt';
$string['templatetypes_format'] = 'Output formats';
$string['templatetypes_fragment'] = 'Fragments';
$string['templatetypes_prompt'] = 'Prompts';
$string['test'] = 'Test';
$string['testfailed'] = 'The test of "{$a}" did not work.';
$string['testimage'] = 'A photorealistic image of a single swan standing on the grass near the edge of a pond.';
$string['testprompt'] = 'Reply with one word: OK';
$string['testspeech'] = 'Hello. This is a test.';
$string['testsucceeded'] = 'The test of "{$a}" worked.';
$string['tokendefaults'] = 'Default values';
$string['tokendefaults_help'] = 'Default values for tokens in the request template, as a JSON object, e.g. {"VOICE": "alloy", "MAXTOKENS": "2000"}. A plugin can give other values when it sends a request. For conversations, VOICES_FEMALE, VOICES_MALE and VOICES are lists of voices, separated by commas. For images, SIZE_SQUARE, SIZE_LANDSCAPE, SIZE_PORTRAIT, RATIO_..., QUALITY_STANDARD and QUALITY_HD give the provider\'s own values.';
$string['tokens'] = 'Tokens (in / out)';
$string['trainingfile'] = 'Training file';
$string['trainingfile_help'] = 'A JSONL file of example conversations, one on each line, at least 10 of them, e.g.

{"messages": [{"role": "system", "content": "You write TOEIC questions."}, {"role": "user", "content": "Write 2 questions about \'deadline\'."}, {"role": "assistant", "content": "::Q1:: ..."}]}

The examples show the model what you want, e.g. questions in your own style and format.';
$string['trymedia'] = 'Make media';
$string['trymedia_help'] = 'Paste a media marker, such as [[IMAGE a description]] or [[AUDIO some text]], or a conversation with a line for each speaker, then lines such as "A: Hello." The media is made by the providers that you can use here, and each request is logged.';
$string['tunedprovider'] = 'Tuned provider';
$string['tuningadded'] = 'The fine-tuning job "{$a}" was added. The training file is sent to the provider, and the job is checked every few minutes. When it has finished, a provider with the tuned model is added.';
$string['tuningcheck'] = 'Check now';
$string['tuningdeleted'] = 'The fine-tuning job "{$a}" was deleted.';
$string['tuningprovider'] = 'Provider';
$string['tuningprovider_help'] = 'The provider whose model is tuned, with its API key. Only your own providers with an OpenAI-style API are listed. Fine-tuning has a cost at the provider.';
$string['tunings'] = 'Fine-tuning';
$string['tuningsharewith'] = 'Share the tuned provider with';
$string['tuningsharewith_help'] = 'When the job has finished, a provider with the tuned model is added, and shared here.';
$string['tuningsintro'] = 'Fine-tuning teaches a model from examples, e.g. to write questions in your own style. Upload a training file of examples for one of your providers; when the job has finished, a provider with the tuned model is added, and can be used like any other provider.';
$string['tuningstatus_failed'] = 'Failed';
$string['tuningstatus_running'] = 'Running';
$string['tuningstatus_succeeded'] = 'Finished';
$string['tuningstatus_uploaded'] = 'Uploaded';
$string['tuningstatus_waiting'] = 'Waiting to upload';
$string['type'] = 'Type';
$string['uploadprotocol'] = 'Upload with';
$string['uploadprotocol_gemini'] = 'Google Gemini (Files API)';
$string['uploadstyle'] = 'Files';
$string['uploadstyle_help'] = 'How files are sent to the provider.

* **In the request**: each file is put in the request, as base64 ({{FILE_BASE64}}). This is simplest, but providers limit the size of a request, e.g. to 20 MB.
* **Uploaded first**: each file is uploaded first, and the request refers to it ({{FILE_URI}}). Use this for large files, such as long recordings and videos.';
$string['uploadstyle_inline'] = 'In the request';
$string['uploadstyle_reference'] = 'Uploaded first';
$string['uploadurl'] = 'Upload URL';
$string['uploadurl_help'] = 'The address to which files are uploaded, e.g. https://generativelanguage.googleapis.com/upload/v1beta/files for Gemini. The API key is sent in a header, so do not put it in this URL.';
$string['url'] = 'URL';
$string['url_help'] = 'The URL that requests are sent to. Tokens: {{MODEL}} and {{APIKEY}}, e.g. for providers that put the model in the URL. Where possible, put the API key in the headers, not the URL, because URLs may be logged, e.g. when a URL is blocked.';
$string['userratelimit'] = 'Requests for each user';
$string['userratelimit_help'] = 'The most requests that one user can send to this provider in one hour. 0 means no limit. The owner of the provider has no limit.';
$string['versionrestored'] = 'Version {$a} was restored, as a new version.';
$string['versions'] = 'Versions';
$string['warningoutsideblocks'] = 'The text of "{$a}" outside its blocks is not used, because it extends another prompt.';
$string['warningreplycleaned'] = 'Text that was not part of the output, such as greetings or markdown, was removed from the reply.';
$string['warningunknownblock'] = 'The block "{$a}" is not in the parent prompt, so it is not used.';
$string['warningunknownparameter'] = 'The parameter "{$a}" has no value.';
$string['wheredatagoes'] = 'Where your data goes';
