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
$string['aibridge:addprovider'] = 'Add AI providers with own API keys, and share them';
$string['aibridge:manageproviders'] = 'Manage all AI providers';
$string['aibridge:useprovider'] = 'Use shared AI providers';
$string['aibridge:viewlog'] = 'View the AI log';
$string['allowexternal'] = 'Use the bridge\'s own providers';
$string['allowexternal_desc'] = 'If this is not set, requests are only sent to Moodle\'s own AI subsystem, and the providers on the "AI providers" page are not used.';
$string['allowuserproviders'] = 'Teachers can add providers';
$string['allowuserproviders_desc'] = 'If this is set, users with the capability "tool/aibridge:addprovider" can add AI providers with their own API keys, and share them in courses and categories. If this is not set, only the site\'s providers are used, and users\' own providers are only used by the users themselves.';
$string['apikey'] = 'API key';
$string['apikey_help'] = 'The secret key from the AI provider. The key is encrypted when it is saved, and it is never shown again, exported, or sent to the browser. Put {{APIKEY}} in the URL or headers where the provider expects the key. If there are no headers, the key is sent as a Bearer token.';
$string['apikeykeep'] = 'New API key';
$string['apikeyset'] = 'Set';
$string['applypreset'] = 'Use these settings';
$string['bridgedisabled'] = 'The AI bridge is turned off, so no requests are sent.';
$string['capability'] = 'Capability';
$string['capability_help'] = 'What the provider does.

* **Text**: makes text from a prompt.
* **Text and files**: makes text from a prompt and files, such as images, audio or PDF files.
* **Speech**: makes speech from text.';
$string['capability_multimodal'] = 'Text and files';
$string['capability_speech'] = 'Speech';
$string['capability_text'] = 'Text';
$string['component'] = 'Plugin';
$string['confirmdelete'] = 'Do you really want to delete the AI provider "{$a}"? Its API key will be deleted too.';
$string['connection'] = 'Connection';
$string['contentretention'] = 'Keep prompts and replies for';
$string['contentretention_desc'] = 'After this time, prompts and replies are removed from the log. The rest of each log record is kept.';
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
$string['editprovider'] = 'Edit an AI provider';
$string['enabled'] = 'Enabled';
$string['enabled_desc'] = 'If this is not set, the AI bridge sends no requests.';
$string['errorcore'] = 'Moodle\'s AI subsystem could not do this request.';
$string['errordisabled'] = 'The AI bridge is turned off.';
$string['errorfiletemplatejson'] = 'The file template is not valid JSON.';
$string['errorfiletemplatemissing'] = 'The request template of "{$a}" has {{FILE_PARTS}}, but there is no file template.';
$string['errorfiletype'] = 'The provider does not take files of type {$a}.';
$string['errorjsonobject'] = 'This must be a JSON object, e.g. {"name": "value"}.';
$string['errorjsontemplate'] = 'This is not valid JSON, even after the {{TOKENS}} are filled in.';
$string['errornegative'] = 'This cannot be less than 0.';
$string['errornocore'] = 'Moodle\'s AI subsystem cannot do this request.';
$string['errornoprompt'] = 'There is no prompt.';
$string['errornoproviders'] = 'No AI provider can do this request here.';
$string['errornotaudio'] = 'The provider did not send back audio.';
$string['errornotext'] = 'There is no text to speak.';
$string['errornotmultimodal'] = 'The provider "{$a}" cannot take files.';
$string['errorpolicynotaccepted'] = 'You must accept the AI policy first.';
$string['errorproviderconfig'] = 'The provider "{$a}" has no URL or no request template.';
$string['errorproviderratelimit'] = 'You have sent the most requests allowed to this provider in one hour. Please try again later.';
$string['errorreplynotext'] = 'The reply from "{$a}" had no text at its return path.';
$string['errorreplynotjson'] = 'The reply from "{$a}" was not JSON.';
$string['errorrequest'] = 'The AI provider could not do the request: {$a}';
$string['errorrequestnotjson'] = 'The request to "{$a}" is not valid JSON. Please check the request template.';
$string['errorshareduntil'] = 'This must be after the date the sharing starts.';
$string['errorsiteratelimit'] = 'The site has sent the most AI requests allowed in one hour. Please try again later.';
$string['errortemplatenofiles'] = 'The request template of "{$a}" has no place for files: add {{FILE_PARTS}}, {{FILE_BASE64}} or {{FILE_URI}}.';
$string['errortemplateonefile'] = 'The request template of "{$a}" can only take one file. Use {{FILE_PARTS}} to send more files.';
$string['errortexttoolong'] = 'The text is too long. The most is {$a} characters.';
$string['errorurl'] = 'This must be a URL that starts with http:// or https://';
$string['erroruserratelimit'] = 'You have sent the most AI requests allowed in one hour. Please try again later.';
$string['externaldisabled'] = 'The site does not use the bridge\'s own providers, so requests are only sent to Moodle\'s own AI subsystem.';
$string['filetemplate'] = 'File template';
$string['filetemplate_help'] = 'The JSON part for one file, which replaces {{FILE_PARTS}} in the request template, once for each file. Tokens: {{FILE_BASE64}}, {{FILE_MIMETYPE}} and {{FILE_NAME}}.

For different kinds of file, use an object of MIME types, e.g. {"image/*": {...}, "application/pdf": {...}}. "*" matches any file.';
$string['generatedby'] = 'Made by AI ({$a})';
$string['headers'] = 'Headers';
$string['headers_help'] = 'The HTTP headers, as a JSON object, e.g. {"x-api-key": "{{APIKEY}}"}. Tokens: {{APIKEY}} and {{MODEL}}. If this is empty, the API key is sent as "Authorization: Bearer ...".';
$string['host'] = 'Data goes to';
$string['log'] = 'AI log';
$string['logprompts'] = 'Keep prompts';
$string['logprompts_desc'] = 'If this is set, the text of each prompt is kept in the log. Prompts may contain personal data, such as students\' writing.';
$string['logresponses'] = 'Keep replies';
$string['logresponses_desc'] = 'If this is set, the text of each reply is kept in the log.';
$string['logretention'] = 'Keep log records for';
$string['logretention_desc'] = 'After this time, log records are deleted.';
$string['model'] = 'Model';
$string['model_help'] = 'The name of the AI model, e.g. "gpt-4o". Use {{MODEL}} in the URL or request template where the provider expects it.';
$string['noproviders'] = 'There are no AI providers here yet.';
$string['owner'] = 'Added by';
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
$string['privacy:metadata:aiprovider'] = 'Prompts and files are sent to AI providers outside Moodle, to make text, speech and other materials.';
$string['privacy:metadata:aiprovider:files'] = 'Files sent with a prompt, such as images or recordings.';
$string['privacy:metadata:aiprovider:prompt'] = 'The text of the prompt, which may include what the user wrote or said.';
$string['privacy:metadata:log'] = 'Each request sent to an AI provider.';
$string['privacy:metadata:log:action'] = 'What was asked for, e.g. text or speech.';
$string['privacy:metadata:log:component'] = 'The plugin that sent the request.';
$string['privacy:metadata:log:contextid'] = 'Where the request was sent from.';
$string['privacy:metadata:log:model'] = 'The AI model.';
$string['privacy:metadata:log:prompt'] = 'The prompt, if the site keeps prompts.';
$string['privacy:metadata:log:providername'] = 'The AI provider.';
$string['privacy:metadata:log:response'] = 'The reply, if the site keeps replies.';
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
$string['provider'] = 'Provider';
$string['providerdeleted'] = 'The AI provider "{$a}" was deleted.';
$string['providers'] = 'AI providers';
$string['providersaved'] = 'The AI provider "{$a}" was saved.';
$string['providersintro'] = 'These AI providers can be used here. Requests go to your own providers first, then to those shared nearest to where the request is made.';
$string['providerwarning'] = 'Requests to this provider are sent outside Moodle, and may contain what users write or say. Only add providers that you trust, and check their data policies. Each request is logged, with the name of the user who sent it.';
$string['ratelimits'] = 'Rate limits';
$string['ratelimits_desc'] = 'The most requests that can be sent in one hour. 0 means no limit.';
$string['removekey'] = 'Remove the API key';
$string['requesttemplate'] = 'Request template';
$string['requesttemplate_help'] = 'The JSON body of each request, with {{TOKENS}} that are filled in when the request is sent. Values are JSON-escaped, so put quotes round a token that is a string, e.g. "{{PROMPT}}", but not round a number, e.g. {{MAXTOKENS}}.

* All requests: {{MODEL}}, {{APIKEY}}, and the tokens in "Default values".
* Text: {{PROMPT}} and {{SYSTEM}}. If there is no {{SYSTEM}}, the system instructions come before the prompt.
* Text and files: {{FILE_PARTS}}, or for one file {{FILE_BASE64}}, {{FILE_MIMETYPE}} and {{FILE_NAME}}.
* Speech: {{TEXT}}, {{VOICE}} and {{LANGUAGE}}.';
$string['requirepolicy'] = 'Users must accept the AI policy';
$string['requirepolicy_desc'] = 'If this is set, each user must accept the AI policy before their requests are sent.';
$string['result'] = 'Result';
$string['returnpath'] = 'Return path';
$string['returnpath_help'] = 'Where to find the reply in the JSON that the provider sends back, as keys separated by commas, e.g. "choices,0,message,content". For speech, leave this empty if the provider sends back the audio itself, or give the path to the base64 audio.';
$string['routes'] = 'Where requests go';
$string['safeguards'] = 'Safeguards';
$string['settings'] = 'Settings';
$string['sharedfrom'] = 'Share from';
$string['shareduntil'] = 'Share until';
$string['shareonlyme'] = 'Only me';
$string['shareonlyowner'] = 'Only the owner';
$string['sharesite'] = 'The whole site';
$string['sharewith'] = 'Share with';
$string['sharewith_help'] = 'Who can use this provider. If it is shared in a course or category, everyone there with the capability "tool/aibridge:useprovider" can use it, and their requests use your API key. Each request is logged with the name of the user who sent it.';
$string['siteratelimit'] = 'Requests for the whole site';
$string['siteratelimit_desc'] = 'The most requests that the whole site can send in one hour.';
$string['siteuserratelimit_desc'] = 'The most requests that one user can send in one hour.';
$string['sortorder'] = 'Sort order';
$string['sortorder_help'] = 'If several providers can do a request, those with a lower number are tried first.';
$string['taskcleanuplog'] = 'Delete old AI log records';
$string['test'] = 'Test';
$string['testfailed'] = 'The test of "{$a}" did not work.';
$string['testprompt'] = 'Reply with one word: OK';
$string['testspeech'] = 'Hello. This is a test.';
$string['testsucceeded'] = 'The test of "{$a}" worked.';
$string['tokendefaults'] = 'Default values';
$string['tokendefaults_help'] = 'Default values for tokens in the request template, as a JSON object, e.g. {"VOICE": "alloy", "MAXTOKENS": "2000"}. A plugin can give other values when it sends a request.';
$string['tokens'] = 'Tokens (in / out)';
$string['url'] = 'URL';
$string['url_help'] = 'The URL that requests are sent to. Tokens: {{MODEL}} and {{APIKEY}}, e.g. for providers that put the model in the URL. Where possible, put the API key in the headers, not the URL, because URLs may be logged, e.g. when a URL is blocked.';
$string['userratelimit'] = 'Requests for each user';
$string['userratelimit_help'] = 'The most requests that one user can send to this provider in one hour. 0 means no limit. The owner of the provider has no limit.';
$string['wheredatagoes'] = 'Where your data goes';
