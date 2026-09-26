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

namespace mod_buchbinder\local;

/**
 * Generates empty worksheets (blank, lined, squared, music staves).
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class blank_page {
    /** @var string[] Available templates. */
    const TEMPLATES = ['blank', 'lined', 'squared', 'staff'];

    /**
     * Render a template as A4 page.
     *
     * @param string $template
     * @param bool $landscape
     * @param int $dpi
     * @return \GdImage
     */
    public static function render(string $template, bool $landscape = false, int $dpi = 150): \GdImage {
        $mm = $dpi / 25.4;
        $w = (int)round(210 * $mm);
        $h = (int)round(297 * $mm);
        if ($landscape) {
            [$w, $h] = [$h, $w];
        }
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        $line = imagecolorallocate($img, 170, 190, 210);
        $dark = imagecolorallocate($img, 60, 60, 60);
        $margin = (int)(15 * $mm);

        switch ($template) {
            case 'lined':
                for ($y = $margin + 8 * $mm; $y < $h - $margin; $y += 8 * $mm) {
                    imageline($img, $margin, (int)$y, $w - $margin, (int)$y, $line);
                }
                break;
            case 'squared':
                for ($y = $margin; $y <= $h - $margin; $y += 5 * $mm) {
                    imageline($img, $margin, (int)$y, $w - $margin, (int)$y, $line);
                }
                for ($x = $margin; $x <= $w - $margin; $x += 5 * $mm) {
                    imageline($img, (int)$x, $margin, (int)$x, $h - $margin, $line);
                }
                break;
            case 'staff':
                $gap = 2 * $mm;
                imagesetthickness($img, max(1, (int)($dpi / 150)));
                for ($top = $margin + 10 * $mm; $top + 4 * $gap < $h - $margin; $top += 20 * $mm) {
                    for ($i = 0; $i < 5; $i++) {
                        $y = (int)($top + $i * $gap);
                        imageline($img, $margin, $y, $w - $margin, $y, $dark);
                    }
                }
                break;
        }
        return $img;
    }
}
