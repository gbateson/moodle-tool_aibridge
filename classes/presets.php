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
 * Ready-made settings for well-known AI providers. Each one fills in the
 * provider form; the user adds the API key and can change anything.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class presets {
    /**
     * Get the presets.
     *
     * @return array preset name => form field => value.
     */
    public static function get_presets(): array {
        $openaiheaders = '{"Authorization": "Bearer {{APIKEY}}"}';
        $claudeheaders = '{"x-api-key": "{{APIKEY}}", "anthropic-version": "2023-06-01"}';
        $geminiheaders = '{"x-goog-api-key": "{{APIKEY}}"}';
        $geminiurl = 'https://generativelanguage.googleapis.com/v1beta/models/{{MODEL}}:generateContent';
        $claudefiles = [
            'image/*' => ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => '{{FILE_MIMETYPE}}',
                    'data' => '{{FILE_BASE64}}']],
            'application/pdf' => ['type' => 'document', 'source' => ['type' => 'base64',
                    'media_type' => 'application/pdf', 'data' => '{{FILE_BASE64}}']],
        ];
        $openaifiles = [
            'image/*' => ['type' => 'image_url', 'image_url' => ['url' => 'data:{{FILE_MIMETYPE}};base64,{{FILE_BASE64}}']],
            'application/pdf' => ['type' => 'file', 'file' => ['filename' => '{{FILE_NAME}}',
                    'file_data' => 'data:application/pdf;base64,{{FILE_BASE64}}']],
        ];
        return [
            // Text.
            'openai' => [
                'name' => 'ChatGPT',
                'capability' => provider::CAPABILITY_TEXT,
                'url' => 'https://api.openai.com/v1/chat/completions',
                'headers' => $openaiheaders,
                'model' => 'gpt-4o',
                'returnpath' => 'choices,0,message,content',
                'requesttemplate' => self::json([
                    'model' => '{{MODEL}}',
                    'messages' => [['role' => 'user', 'content' => '{{PROMPT}}']],
                ]),
            ],
            'claude' => [
                'name' => 'Claude',
                'capability' => provider::CAPABILITY_TEXT,
                'url' => 'https://api.anthropic.com/v1/messages',
                'headers' => $claudeheaders,
                'model' => 'claude-sonnet-5-5',
                'returnpath' => 'content,0,text',
                'tokendefaults' => self::json(['MAXTOKENS' => '4000']),
                'requesttemplate' => self::json([
                    'model' => '{{MODEL}}',
                    'max_tokens' => '{{MAXTOKENS}}',
                    'messages' => [['role' => 'user', 'content' => '{{PROMPT}}']],
                ]),
            ],
            'gemini' => [
                'name' => 'Gemini',
                'capability' => provider::CAPABILITY_TEXT,
                'url' => $geminiurl,
                'headers' => $geminiheaders,
                'model' => 'gemini-2.5-flash',
                'returnpath' => 'candidates,0,content,parts,0,text',
                'requesttemplate' => self::json([
                    'contents' => [['parts' => [['text' => '{{PROMPT}}']]]],
                ]),
            ],
            'deepseek' => [
                'name' => 'DeepSeek',
                'capability' => provider::CAPABILITY_TEXT,
                'url' => 'https://api.deepseek.com/chat/completions',
                'headers' => $openaiheaders,
                'model' => 'deepseek-chat',
                'returnpath' => 'choices,0,message,content',
                'requesttemplate' => self::json([
                    'model' => '{{MODEL}}',
                    'messages' => [['role' => 'user', 'content' => '{{PROMPT}}']],
                ]),
            ],
            'ollama' => [
                'name' => 'Ollama',
                'capability' => provider::CAPABILITY_TEXT,
                'url' => 'http://localhost:11434/api/chat',
                'headers' => '{}',
                'model' => 'llama3.2',
                'returnpath' => 'message,content',
                'requesttemplate' => self::json([
                    'model' => '{{MODEL}}',
                    'messages' => [['role' => 'user', 'content' => '{{PROMPT}}']],
                    'stream' => false,
                ]),
            ],

            // Text from text and files.
            'geminifiles' => [
                'name' => 'Gemini (files)',
                'capability' => provider::CAPABILITY_MULTIMODAL,
                'url' => $geminiurl,
                'headers' => $geminiheaders,
                'model' => 'gemini-2.5-flash',
                'returnpath' => 'candidates,0,content,parts,0,text',
                'requesttemplate' => self::json([
                    'contents' => [['parts' => ['{{FILE_PARTS}}', ['text' => '{{PROMPT}}']]]],
                ]),
                // Gemini takes audio, video, images and PDFs in the same way.
                'filetemplate' => self::json(['inline_data' => ['mime_type' => '{{FILE_MIMETYPE}}', 'data' => '{{FILE_BASE64}}']]),
                'uploadstyle' => provider::UPLOAD_INLINE_BASE64,
            ],
            'openaifiles' => [
                'name' => 'ChatGPT (files)',
                'capability' => provider::CAPABILITY_MULTIMODAL,
                'url' => 'https://api.openai.com/v1/chat/completions',
                'headers' => $openaiheaders,
                'model' => 'gpt-4o',
                'returnpath' => 'choices,0,message,content',
                'requesttemplate' => self::json([
                    'model' => '{{MODEL}}',
                    'messages' => [['role' => 'user', 'content' => ['{{FILE_PARTS}}', ['type' => 'text', 'text' => '{{PROMPT}}']]]],
                ]),
                'filetemplate' => self::json($openaifiles),
                'uploadstyle' => provider::UPLOAD_INLINE_BASE64,
            ],
            'claudefiles' => [
                'name' => 'Claude (files)',
                'capability' => provider::CAPABILITY_MULTIMODAL,
                'url' => 'https://api.anthropic.com/v1/messages',
                'headers' => $claudeheaders,
                'model' => 'claude-sonnet-5-5',
                'returnpath' => 'content,0,text',
                'tokendefaults' => self::json(['MAXTOKENS' => '4000']),
                'requesttemplate' => self::json([
                    'model' => '{{MODEL}}',
                    'max_tokens' => '{{MAXTOKENS}}',
                    'messages' => [['role' => 'user', 'content' => ['{{FILE_PARTS}}', ['type' => 'text', 'text' => '{{PROMPT}}']]]],
                ]),
                'filetemplate' => self::json($claudefiles),
                'uploadstyle' => provider::UPLOAD_INLINE_BASE64,
            ],

            // Images.
            'openaiimage' => [
                'name' => 'OpenAI images',
                'capability' => provider::CAPABILITY_IMAGE,
                'url' => 'https://api.openai.com/v1/images/generations',
                'headers' => $openaiheaders,
                'model' => 'gpt-image-1',
                'returnpath' => 'data,0,b64_json',
                'tokendefaults' => self::json([
                    'SIZE_SQUARE' => '1024x1024', 'SIZE_LANDSCAPE' => '1536x1024', 'SIZE_PORTRAIT' => '1024x1536',
                    'QUALITY_STANDARD' => 'medium', 'QUALITY_HD' => 'high',
                ]),
                'requesttemplate' => self::json([
                    'model' => '{{MODEL}}',
                    'prompt' => '{{PROMPT}}',
                    'size' => '{{SIZE}}',
                    'quality' => '{{QUALITY}}',
                    'n' => 1,
                ]),
            ],
            'dalle3' => [
                'name' => 'DALL-E 3',
                'capability' => provider::CAPABILITY_IMAGE,
                'url' => 'https://api.openai.com/v1/images/generations',
                'headers' => $openaiheaders,
                'model' => 'dall-e-3',
                'returnpath' => 'data,0,b64_json',
                'tokendefaults' => self::json([
                    'SIZE_SQUARE' => '1024x1024', 'SIZE_LANDSCAPE' => '1792x1024', 'SIZE_PORTRAIT' => '1024x1792',
                    'STYLE' => 'natural',
                ]),
                'requesttemplate' => self::json([
                    'model' => '{{MODEL}}',
                    'prompt' => '{{PROMPT}}',
                    'size' => '{{SIZE}}',
                    'quality' => '{{QUALITY}}',
                    'style' => '{{STYLE}}',
                    'response_format' => 'b64_json',
                    'n' => 1,
                ]),
            ],
            'imagen' => [
                'name' => 'Imagen',
                'capability' => provider::CAPABILITY_IMAGE,
                'url' => 'https://generativelanguage.googleapis.com/v1beta/models/{{MODEL}}:predict',
                'headers' => $geminiheaders,
                'model' => 'imagen-4.0-generate-001',
                'returnpath' => 'predictions,0,bytesBase64Encoded',
                'tokendefaults' => self::json([
                    'RATIO_SQUARE' => '1:1', 'RATIO_LANDSCAPE' => '16:9', 'RATIO_PORTRAIT' => '9:16',
                ]),
                'requesttemplate' => self::json([
                    'instances' => [['prompt' => '{{PROMPT}}']],
                    'parameters' => ['sampleCount' => 1, 'aspectRatio' => '{{RATIO}}'],
                ]),
            ],

            // Speech.
            'openaitts' => [
                'name' => 'OpenAI speech',
                'capability' => provider::CAPABILITY_SPEECH,
                'url' => 'https://api.openai.com/v1/audio/speech',
                'headers' => $openaiheaders,
                'model' => 'gpt-4o-mini-tts',
                'returnpath' => '',
                'tokendefaults' => self::json([
                    'VOICE' => 'alloy',
                    'STYLE' => 'Speak clearly, at a natural pace.',
                    'AUDIOFORMAT' => 'mp3',
                    'VOICES_FEMALE' => 'nova,shimmer,coral,sage',
                    'VOICES_MALE' => 'onyx,echo,ash,fable',
                ]),
                'requesttemplate' => self::json([
                    'model' => '{{MODEL}}',
                    'input' => '{{TEXT}}',
                    'instructions' => '{{STYLE}}',
                    'voice' => '{{VOICE}}',
                    'response_format' => '{{AUDIOFORMAT}}',
                ]),
            ],
            'geminitts' => [
                'name' => 'Gemini speech',
                'capability' => provider::CAPABILITY_SPEECH,
                'url' => $geminiurl,
                'headers' => $geminiheaders,
                'model' => 'gemini-2.5-flash-preview-tts',
                'returnpath' => 'candidates,0,content,parts,0,inlineData,data',
                'tokendefaults' => self::json([
                    'VOICE' => 'Kore',
                    'STYLE' => 'Say this clearly',
                    'VOICES_FEMALE' => 'Kore,Aoede,Leda,Zephyr',
                    'VOICES_MALE' => 'Puck,Charon,Fenrir,Orus',
                ]),
                'requesttemplate' => self::json([
                    'contents' => [['parts' => [['text' => '{{STYLE}}: {{TEXT}}']]]],
                    'generationConfig' => [
                        'responseModalities' => ['AUDIO'],
                        'speechConfig' => ['voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => '{{VOICE}}']]],
                    ],
                ]),
            ],
            'elevenlabs' => [
                'name' => 'ElevenLabs',
                'capability' => provider::CAPABILITY_SPEECH,
                'url' => 'https://api.elevenlabs.io/v1/text-to-speech/{{VOICE}}',
                'headers' => '{"xi-api-key": "{{APIKEY}}", "Accept": "audio/mpeg"}',
                'model' => 'eleven_multilingual_v2',
                'returnpath' => '',
                'tokendefaults' => self::json([
                    'VOICE' => '21m00Tcm4TlvDq8ikWAM',
                    'VOICES_FEMALE' => '21m00Tcm4TlvDq8ikWAM,EXAVITQu4vr4xnSDxMaL',
                    'VOICES_MALE' => 'pNInz6obpgDQGcFmaJgB,ErXwobaYiN019PkySvjV',
                ]),
                'requesttemplate' => self::json([
                    'text' => '{{TEXT}}',
                    'model_id' => '{{MODEL}}',
                ]),
            ],
        ];
    }

    /**
     * Get the preset menu for the provider form.
     *
     * @return string[] preset name => label.
     */
    public static function get_menu(): array {
        $menu = ['' => get_string('choosedots')];
        foreach (self::get_presets() as $name => $preset) {
            $menu[$name] = $preset['name'] . ' (' . get_string('capability_' . $preset['capability'], 'tool_aibridge') . ')';
        }
        return $menu;
    }

    /**
     * Write a template as readable JSON. A list item of "{{FILE_PARTS}}"
     * becomes the token itself, not a JSON string, because it is replaced
     * by the file parts, each followed by a comma. A value of "{{MAXTOKENS}}"
     * loses its quotes, because the number is put there as it is.
     *
     * @param array $template
     * @return string
     */
    public static function json(array $template): string {
        $json = json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        // JSON_PRETTY_PRINT indents with 4 spaces; 2 is easier to read in a text area.
        $json = preg_replace_callback('/^( +)/m', function ($matches) {
            return str_repeat(' ', strlen($matches[1]) / 2);
        }, $json);
        $json = preg_replace('/"\{\{FILE_PARTS\}\}",?\s*/', '{{FILE_PARTS}} ', $json);
        return str_replace('"{{MAXTOKENS}}"', '{{MAXTOKENS}}', $json);
    }
}
