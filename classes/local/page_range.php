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
 * Parses page excerpts such as "5-12, 15, 20-".
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page_range {
    /**
     * Check the syntax of a range expression. Empty means "all pages".
     *
     * @param string|null $range
     * @return bool
     */
    public static function is_valid(?string $range): bool {
        $range = trim((string)$range);
        if ($range === '') {
            return true;
        }
        foreach (explode(',', $range) as $part) {
            if (!preg_match('/^\s*(\d+)\s*(-\s*(\d*)\s*)?$/', $part, $m)) {
                return false;
            }
            if ((int)$m[1] < 1) {
                return false;
            }
            if (!empty($m[3]) && (int)$m[3] < (int)$m[1]) {
                return false;
            }
        }
        return true;
    }

    /**
     * Resolve a range expression to sorted, unique 1-based page numbers.
     *
     * @param string|null $range
     * @param int $total number of pages in the document
     * @return int[]
     */
    public static function parse(?string $range, int $total): array {
        $range = trim((string)$range);
        if ($total < 1) {
            return [];
        }
        if ($range === '' || !self::is_valid($range)) {
            return range(1, $total);
        }
        $pages = [];
        foreach (explode(',', $range) as $part) {
            preg_match('/^\s*(\d+)\s*(-\s*(\d*)\s*)?$/', $part, $m);
            $from = (int)$m[1];
            if (!isset($m[2])) {
                $to = $from;
            } else if (!isset($m[3]) || $m[3] === '') {
                $to = $total;
            } else {
                $to = (int)$m[3];
            }
            $to = min($to, $total);
            for ($i = $from; $i <= $to; $i++) {
                $pages[$i] = $i;
            }
        }
        ksort($pages);
        return array_values($pages);
    }
}
