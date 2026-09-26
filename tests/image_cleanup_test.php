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

use mod_buchbinder\local\image_cleanup;

/**
 * Tests for scan optimisation.
 *
 * @package    mod_buchbinder
 * @category   test
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_buchbinder\local\image_cleanup
 */
final class image_cleanup_test extends \basic_testcase {
    /**
     * Synthetic page with lines of "text".
     *
     * @param int $w
     * @param int $h
     * @param float $tilt degrees
     * @param bool $gutter leave an empty band in the middle
     * @param int $border black border width
     * @return \GdImage
     */
    protected function page(int $w, int $h, float $tilt = 0, bool $gutter = false, int $border = 0): \GdImage {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, 0xFFFFFF);
        for ($l = 0; $l < 30; $l++) {
            $y0 = 80 + $l * 30;
            for ($x = 60; $x < $w - 60; $x++) {
                if (($gutter && abs($x - $w / 2) < 40) || ((int)($x / 7)) % 5 == 4) {
                    continue;
                }
                $y = (int)($y0 + $x * tan(deg2rad($tilt)));
                imagefilledrectangle($im, $x, $y, $x, $y + 6, 0x000000);
            }
        }
        if ($border) {
            imagefilledrectangle($im, 0, 0, $border, $h, 0);
            imagefilledrectangle($im, 0, 0, $w, $border, 0);
        }
        return $im;
    }

    public function test_deskew(): void {
        $img = $this->page(700, 1000, 2);
        $this->assertEqualsWithDelta(2, image_cleanup::detect_skew($img), 0.3);
        $this->assertEqualsWithDelta(0, image_cleanup::detect_skew(image_cleanup::deskew($img)), 0.3);
    }

    public function test_chop_borders(): void {
        $img = image_cleanup::chop_borders($this->page(700, 1000, 0, false, 30));
        $this->assertLessThan(700, imagesx($img));
        $this->assertLessThan(1000, imagesy($img));
        $this->assertGreaterThan(600, imagesx($img));
    }

    public function test_gutter(): void {
        $this->assertEqualsWithDelta(800, image_cleanup::find_gutter($this->page(1600, 1000, 0, true)), 40);
        // Content crossing the middle (map, wide table) is not split.
        $this->assertNull(image_cleanup::find_gutter($this->page(1600, 1000)));
        // Portrait pages are never double pages.
        $this->assertNull(image_cleanup::find_gutter($this->page(700, 1000, 0, true)));
    }
}
