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
 * The templates that the AI bridge ships: a prompt to generate questions,
 * GIFT formats for it, and fragments that other templates can include.
 *
 * Other plugins can extend "tool_aibridge:generate-questions" by giving
 * their own {{block:description}}, and can include the fragments.
 *
 * @package    tool_aibridge
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// The templates are text for an AI, in which line breaks matter, so their lines are long.
// phpcs:disable moodle.Files.LineLength

$templates = [

    // Fragments.
    'output-rules' => [
        'type' => 'fragment',
        'name' => 'Output rules',
        'description' => 'Tells the AI to reply with the questions only, so that they can be imported.',
        'body' => <<<'EOD'
Follow these important instructions:
[1] In your response, do not include any greetings, explanations or platitudes.
[2] In your response, do not use any markdown, such as code fences (backticks), headings (repeated sharp signs), or bold and italic text (asterisks or underlines). The symbols in the template below, such as = ~ # { and }, are part of the format, and must be kept.
[3] Replace each placeholder in the template, such as question-name or correct-answer, with your own text.
[4] Format the questions in Moodle's {{question-format}} format using the template below.
EOD
,
    ],
    'multilang-feedback' => [
        'type' => 'fragment',
        'name' => 'Bilingual feedback',
        'description' => 'Asks for feedback in two languages, for Moodle\'s multi-language content filter.',
        'body' => <<<'EOD'
All these items are bilingual ({{feedback-language-1}} and {{feedback-language-2}}) strings that are formatted for the Moodle multi-language content filter using "span" tags, as in the following example:
#<span lang="{{feedback-lang-1}}" class="multilang">{{feedback-language-1}} feedback</span><span lang="{{feedback-lang-2}}" class="multilang">{{feedback-language-2}} feedback</span>
EOD
,
        'parameters' => [
            ['name' => 'feedback-language-1', 'default' => 'English', 'description' => 'The first language of the feedback.'],
            ['name' => 'feedback-lang-1', 'default' => 'en', 'description' => 'The language code of the first language.'],
            ['name' => 'feedback-language-2', 'default' => 'Japanese', 'description' => 'The second language of the feedback.'],
            ['name' => 'feedback-lang-2', 'default' => 'ja', 'description' => 'The language code of the second language.'],
        ],
    ],
    'image-prompt-rules' => [
        'type' => 'fragment',
        'name' => 'Image prompt rules',
        'description' => 'How to write a safe, clear prompt for an AI image generator, in an [[IMAGE ...]] marker.',
        'body' => <<<'EOD'
The "image-prompt" is a text prompt to generate an image in an AI image generator such as DALL-E 3. Clearly describe the main subjects, their position in the scene, and key details about their appearance and actions. Use precise spatial terms like "on the ground", "next to", or "at the edge of" to avoid ambiguity about positioning. Focus on a single subject or a small number of people to avoid overly complex scenes. Make descriptions of actions and appearance detailed, such as "standing upright with feet visible on the grass". Add explicit details about the subject's position, posture, or interaction with their environment.

When writing the "image-prompt", avoid any content that may be considered inappropriate or offensive, so that the image follows content policies. For example, do not use any of the following words: boy, girl, child, children. Avoid content that is not safe for work, copyrighted characters, and political, controversial or sensitive topics. Focus on safe, universally acceptable themes, without explicit, violent or inappropriate content.

The "image-prompt" is always enclosed in double square brackets, as in the following examples:

[[IMAGE A photorealistic image of a man and a woman in a modern office with bright overhead lighting, standing next to a large printer that has an error message displayed on its screen. The man is wearing a blue shirt and tie, looking at the printer with a frustrated expression. The woman is wearing a white blouse and black skirt. She is holding a troubleshooting manual, and she appears to be concerned. Behind them, desks with computers and potted plants are visible, creating a professional and clean office environment.]]

[[IMAGE A photorealistic image of a single swan standing on the ground near the edge of a pond. The swan is positioned with its feet visible on the grass, its neck extended as it looks toward the sky. Around the pond are colorful flowers that stand out against the serene setting.]]
EOD
,
    ],

    // Formats.
    'gift-multichoice' => [
        'type' => 'format',
        'name' => 'GIFT format (Multiple choice, EN)',
        'qformat' => 'gift',
        'body' => <<<'EOD'
::question-name::question-text {
    =correct-answer #Explanation of why the answer is correct.
    ~wrong-answer-1 #Explanation of why the answer is wrong and hint about the correct answer.
    ~wrong-answer-2 #Explanation of why the answer is wrong and hint about the correct answer.
    ~wrong-answer-3 #Explanation of why the answer is wrong and hint about the correct answer.
}
EOD
,
    ],
    'gift-truefalse' => [
        'type' => 'format',
        'name' => 'GIFT format (True/False, EN)',
        'qformat' => 'gift',
        'body' => <<<'EOD'
::question-name::statement-that-is-true{TRUE #Explanation of why this answer is TRUE}
::question-name::statement-that-is-false{FALSE #Explanation of why this answer is FALSE}
EOD
,
    ],
    'gift-match' => [
        'type' => 'format',
        'name' => 'GIFT format (Matching, EN)',
        'qformat' => 'gift',
        'body' => <<<'EOD'
::question-name::Match the following words with their corresponding meanings. {
    =target-word -> definition-of-target-word
    =similar-word-1 -> definition-of-similar-word-1
    =similar-word-2 -> definition-of-similar-word-2
    =similar-word-3 -> definition-of-similar-word-3
}
EOD
,
    ],

    // Prompts.
    'generate-questions' => [
        'type' => 'prompt',
        'name' => 'Generate GIFT/XML questions',
        'description' => 'Questions about one vocabulary item. Other prompts can extend this one by giving their own description block.',
        'body' => <<<'EOD'
{{block:role}}I am a language teacher. I teach {{target-language}} to {{student-nationality}} students at {{school-level}}.
Your role is an expert in generating online educational content.
Help me to create questions that I can import into a Moodle LMS.{{endblock}}
{{block:task}}Create {{number-of-questions}} {{type-of-questions}} questions to check students' understanding of the vocabulary item "{{vocabulary-item}}".
Use only CEFR level {{level-of-questions}} {{target-language}}.{{endblock}}

{{block:description}}{{endblock}}

{{block:rules}}{{include:tool_aibridge:output-rules}}{{endblock}}

{{output-format}}
EOD
,
        'parameters' => [
            ['name' => 'target-language', 'default' => 'English', 'required' => true,
                'description' => 'The language that the students are learning.'],
            ['name' => 'student-nationality', 'default' => 'Japanese', 'description' => 'The nationality of the students.'],
            ['name' => 'school-level', 'default' => 'university', 'description' => 'The level of the school.'],
            ['name' => 'vocabulary-item', 'required' => true, 'description' => 'The word or phrase to ask about.'],
            ['name' => 'number-of-questions', 'default' => '4', 'required' => true, 'description' => 'How many questions to make.'],
            ['name' => 'level-of-questions', 'default' => 'B1', 'description' => 'The CEFR level of the questions, e.g. A2.'],
        ],
        'formatlinks' => [
            'multichoice' => 'gift-multichoice',
            'truefalse' => 'gift-truefalse',
            'match' => 'gift-match',
        ],
    ],
];
