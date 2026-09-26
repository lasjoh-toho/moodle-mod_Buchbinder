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
 * Booklet geometry: every document is laid out as a booklet of left and right pages.
 *
 * With "first page right" (the default, like a printed book) page 1 is a right page and the
 * following pages form double pages 2|3, 4|5, … Margins are mirrored: the inner margin (gutter)
 * lies at the binding.
 *
 * All values are relative to the page (0..1), the page is A4 portrait.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class booklet {
    /** @var string Left (even) page. */
    const LEFT = 'left';
    /** @var string Right (odd) page. */
    const RIGHT = 'right';

    /** @var float Page width in mm. */
    const PAGE_WIDTH_MM = 210;
    /** @var float Page height in mm. */
    const PAGE_HEIGHT_MM = 297;

    /** @var array Margins in mm (type area). */
    const MARGINS_MM = ['top' => 20, 'bottom' => 25, 'inner' => 25, 'outer' => 18];

    /** @var string Page style: type area of a regular book page. */
    const STYLE_STANDARD = 'standard';
    /** @var string Page style after Edward Tufte: narrow text column, wide outer margin for notes and small figures. */
    const STYLE_TUFTE = 'tufte';

    /** @var array Margins in mm of the Tufte style; the outer margin holds the note column. */
    const MARGINS_TUFTE_MM = ['top' => 20, 'bottom' => 25, 'inner' => 20, 'outer' => 68];
    /** @var float Space between text column and note column in mm. */
    const NOTE_GAP_MM = 7;
    /** @var float Space between note column and the outer edge of the page in mm. */
    const NOTE_EDGE_MM = 12;

    /** @var int Pixel size of canvas pages (A4 at 150 dpi). */
    const CANVAS_WIDTH = 1240;
    /** @var int Pixel size of canvas pages (A4 at 150 dpi). */
    const CANVAS_HEIGHT = 1754;

    /**
     * Side of a page.
     *
     * @param int $position 1-based position in the document
     * @param bool $firstright whether page 1 is a right page
     * @return string left or right
     */
    public static function side(int $position, bool $firstright = true): string {
        $odd = $position % 2 === 1;
        return ($odd === $firstright) ? self::RIGHT : self::LEFT;
    }

    /**
     * Type area (content box) of a page, mirrored for left and right pages.
     *
     * @param string $side
     * @param string|null $style page style (STYLE_STANDARD or STYLE_TUFTE)
     * @return float[] x, y, w, h relative to the page
     */
    public static function type_area(string $side, ?string $style = null): array {
        $m = $style === self::STYLE_TUFTE ? self::MARGINS_TUFTE_MM : self::MARGINS_MM;
        $left = $side === self::RIGHT ? $m['inner'] : $m['outer'];
        $right = $side === self::RIGHT ? $m['outer'] : $m['inner'];
        return [
            $left / self::PAGE_WIDTH_MM,
            $m['top'] / self::PAGE_HEIGHT_MM,
            (self::PAGE_WIDTH_MM - $left - $right) / self::PAGE_WIDTH_MM,
            (self::PAGE_HEIGHT_MM - $m['top'] - $m['bottom']) / self::PAGE_HEIGHT_MM,
        ];
    }

    /**
     * Note column in the outer margin of Tufte style pages.
     *
     * The outer margin is on the right of right pages and on the left of left pages, so notes are
     * always on the outside of the double page, also in print.
     *
     * @param string $side
     * @return float[] x, y, w, h relative to the page
     */
    public static function note_area(string $side): array {
        $m = self::MARGINS_TUFTE_MM;
        $width = $m['outer'] - self::NOTE_GAP_MM - self::NOTE_EDGE_MM;
        $x = $side === self::RIGHT ? self::PAGE_WIDTH_MM - self::NOTE_EDGE_MM - $width : self::NOTE_EDGE_MM;
        return [
            $x / self::PAGE_WIDTH_MM,
            $m['top'] / self::PAGE_HEIGHT_MM,
            $width / self::PAGE_WIDTH_MM,
            (self::PAGE_HEIGHT_MM - $m['top'] - $m['bottom']) / self::PAGE_HEIGHT_MM,
        ];
    }

    /**
     * Known page styles.
     *
     * @return string[]
     */
    public static function styles(): array {
        return [self::STYLE_STANDARD, self::STYLE_TUFTE];
    }

    /**
     * Group page positions into double pages for display.
     *
     * A right page starts a new row unless it follows a left page; protected landscape
     * pages always stand alone.
     *
     * @param array $pages list of ['position' => int, 'landscape' => bool, ...]
     * @param bool $firstright
     * @return array[] rows, each a list of pages (1 or 2)
     */
    public static function spreads(array $pages, bool $firstright = true): array {
        $rows = [];
        $open = null;
        foreach ($pages as $page) {
            $side = self::side($page['position'], $firstright);
            $prev = $open !== null ? $rows[$open][0] : null;
            if (!empty($page['landscape'])) {
                $rows[] = [$page];
                $open = null;
            } else if ($side === self::RIGHT && $prev !== null && $prev['position'] === $page['position'] - 1) {
                $rows[$open][] = $page;
                $open = null;
            } else {
                $rows[] = [$page];
                $open = $side === self::LEFT ? count($rows) - 1 : null;
            }
        }
        return $rows;
    }
}
