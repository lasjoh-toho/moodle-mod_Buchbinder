<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_buchbinder;

use mod_buchbinder\local\page_range;
use mod_buchbinder\local\imposition;

/**
 * Tests for page ranges and print imposition.
 *
 * @package    mod_buchbinder
 * @category   test
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_buchbinder\local\page_range
 * @covers     \mod_buchbinder\local\imposition
 */
final class layout_test extends \basic_testcase {
    public function test_page_range(): void {
        $this->assertSame([1, 2, 3], page_range::parse('', 3));
        $this->assertSame([2, 5, 6, 7, 8, 10, 11, 12], page_range::parse('5-8, 2, 10-', 12));
        $this->assertSame([3, 4], page_range::parse('3-99', 4));
        $this->assertSame([], page_range::parse('1-3', 0));
        $this->assertTrue(page_range::is_valid(' 1 - 4 ,7'));
        $this->assertFalse(page_range::is_valid('4-1'));
        $this->assertFalse(page_range::is_valid('0'));
        $this->assertFalse(page_range::is_valid('a-b'));
    }

    public function test_booklet(): void {
        // 6 pages are padded to 8: sheet 1 front 8|1, back 2|7; sheet 2 front 6|3, back 4|5.
        $this->assertSame([[null, 1], [2, null], [6, 3], [4, 5]], imposition::booklet(6));
        $this->assertCount(2, imposition::booklet(4));
    }

    public function test_nup(): void {
        // Page 1 is a right page: it goes into the right slot so that double pages stay together.
        $this->assertSame([[null, 1], [2, 3]], imposition::sides(imposition::LAYOUT_2UP, 3));
        $this->assertSame([[null, 1, 2, 3], [4, 5, null, null]], imposition::sides(imposition::LAYOUT_4UP, 5));
        $this->assertSame([[1, 2], [3, null]], imposition::sides(imposition::LAYOUT_2UP, 3, false));
        $this->assertSame([[1], [2]], imposition::sides(imposition::LAYOUT_1UP, 2));
        // A booklet starting with a left page gets a blank front page.
        $this->assertSame([[null, null], [1, null]], imposition::sides(imposition::LAYOUT_BOOKLET, 1, false));
        $this->assertSame(imposition::booklet(6), imposition::sides(imposition::LAYOUT_BOOKLET, 6));
    }
}
