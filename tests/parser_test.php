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

use tool_aibridge\library\parser;

#[\PHPUnit\Framework\Attributes\CoversClass(\tool_aibridge\library\parser::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_aibridge\library\parsed::class)]
/**
 * Tests for reading replies.
 *
 * @package    tool_aibridge
 * @category   test
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_aibridge\library\parser
 * @covers     \tool_aibridge\library\parsed
 */
final class parser_test extends \advanced_testcase {
    /**
     * Chatter and code fences are removed.
     */
    public function test_clean(): void {
        $fence = str_repeat(chr(96), 3);
        $reply = "Sure! Here:\n{$fence}gift\n::Q1::A {\n=b\n~c\n}\n{$fence}\nEnjoy.";
        $this->assertSame("::Q1::A {\n=b\n~c\n}", parser::clean($reply, 'gift'));
        $this->assertSame('[1, 2]', parser::clean("Here you are: [1, 2] Done.", 'json'));
        $this->assertSame(
            '<quiz><question type="essay"/></quiz>',
            parser::clean("OK\n<quiz><question type=\"essay\"/></quiz>\nBye", 'xml')
        );
        $this->assertSame('<div>{1:MCVS:=a~b}</div>', parser::clean("Here:\n<div>{1:MCVS:=a~b}</div>\nThanks", 'multianswer'));
    }

    /**
     * GIFT is checked with Moodle's GIFT reader.
     */
    public function test_gift(): void {
        $this->resetAfterTest();
        $parsed = parser::parse("::Q1::Pick one {\n=right\n~wrong\n}\n\n::Q2::True? {TRUE}", 'gift');
        $this->assertTrue($parsed->is_valid(), implode(' ', $parsed->get_errors()));
        $this->assertSame(2, $parsed->get_count());

        $parsed = parser::parse("::Q1::Broken {\n=right\n~wrong\n", 'gift');
        $this->assertFalse($parsed->is_valid());

        $parsed = parser::parse('No questions here.', 'gift');
        $this->assertFalse($parsed->is_valid());
    }

    /**
     * Embedded answers, XML and JSON are checked.
     */
    public function test_other_formats(): void {
        $parsed = parser::parse('<p>Choose: {1:MCVS:=a~b} and {1:SA:=c}</p>', 'multianswer');
        $this->assertSame(2, $parsed->get_count());
        $this->assertFalse(parser::parse('<p>No gaps</p>', 'multianswer')->is_valid());

        $xml = '<quiz><question type="category"/><question type="multichoice"/><question type="essay"/></quiz>';
        $this->assertSame(2, parser::parse($xml, 'xml')->get_count());
        $this->assertFalse(parser::parse('<quiz><question>', 'xml')->is_valid());

        $this->assertSame(3, parser::parse('[{"a":1},{"a":2},{"a":3}]', 'json')->get_count());
        $this->assertFalse(parser::parse('{"a":', 'json')->is_valid());
    }

    /**
     * Media markers are found, with their attributes.
     */
    public function test_media(): void {
        $media = parser::find_media("[[IMAGE A swan.]] then [[AUDIO class=\"nomediaplugin\"\n  A=woman\n  A: Hello.\n]]");
        $this->assertCount(2, $media);
        $this->assertSame('IMAGE', $media[0]['type']);
        $this->assertSame('A swan.', $media[0]['text']);
        $this->assertSame('class="nomediaplugin"', $media[1]['attributes']);
        $this->assertSame("A=woman\n  A: Hello.", $media[1]['text']);
    }
}
