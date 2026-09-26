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
 * Page imposition for the eco print engine (booklet, 2-up, 4-up).
 *
 * All methods work on positions 1..n of the pages that are printed and return
 * one array per printed sheet side. Blank slots are null.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class imposition {
    /** @var string One page per sheet side. */
    const LAYOUT_1UP = '1up';
    /** @var string Two pages side by side (landscape sheet). */
    const LAYOUT_2UP = '2up';
    /** @var string Four pages in a 2x2 grid. */
    const LAYOUT_4UP = '4up';
    /** @var string Saddle stitched booklet: fold the stack in the middle. */
    const LAYOUT_BOOKLET = 'booklet';

    /**
     * Available layouts.
     *
     * @return string[]
     */
    public static function layouts(): array {
        return [self::LAYOUT_1UP, self::LAYOUT_2UP, self::LAYOUT_4UP, self::LAYOUT_BOOKLET];
    }

    /**
     * Number of page slots per sheet side for a layout.
     *
     * @param string $layout
     * @return int
     */
    public static function slots(string $layout): int {
        switch ($layout) {
            case self::LAYOUT_2UP:
            case self::LAYOUT_BOOKLET:
                return 2;
            case self::LAYOUT_4UP:
                return 4;
            default:
                return 1;
        }
    }

    /**
     * Compute the sheet sides for a layout.
     *
     * Several pages on one sheet side are placed as double pages: left pages on the left, right
     * pages on the right, so the outer margins (and the notes of the Tufte style) lie outside.
     *
     * @param string $layout
     * @param int $count number of pages
     * @param bool $startright whether the first page is a right page of the booklet
     * @return array[] list of sides, each a list of page positions (1-based) or null
     */
    public static function sides(string $layout, int $count, bool $startright = true): array {
        if ($count < 1) {
            return [];
        }
        $positions = range(1, $count);
        if ($layout === self::LAYOUT_BOOKLET) {
            // The first page of a booklet is a right page (front cover).
            if (!$startright) {
                array_unshift($positions, null);
            }
            return array_map(
                fn($side) => array_map(fn($p) => $p === null ? null : $positions[$p - 1], $side),
                self::booklet(count($positions))
            );
        }
        $slots = self::slots($layout);
        if ($slots > 1 && $startright) {
            // Page 1 goes into the right slot, like the first page of a book.
            array_unshift($positions, null);
        }
        $sides = [];
        foreach (array_chunk($positions, $slots) as $chunk) {
            $sides[] = array_pad($chunk, $slots, null);
        }
        return $sides;
    }

    /**
     * Booklet order: pages padded to a multiple of four, printed duplex
     * (flip on short edge), folded in the middle.
     *
     * @param int $count
     * @return array[]
     */
    public static function booklet(int $count): array {
        $n = (int)(ceil($count / 4) * 4);
        $sides = [];
        for ($sheet = 0; $sheet < $n / 4; $sheet++) {
            $front = [$n - 2 * $sheet, 2 * $sheet + 1];
            $back = [2 * $sheet + 2, $n - 2 * $sheet - 1];
            foreach ([$front, $back] as $side) {
                $sides[] = array_map(fn($p) => $p <= $count ? $p : null, $side);
            }
        }
        return $sides;
    }
}
