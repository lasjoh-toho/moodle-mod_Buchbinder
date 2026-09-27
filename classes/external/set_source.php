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

namespace mod_buchbinder\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_buchbinder\local\document;
use mod_buchbinder\local\importer;

/**
 * Set a continuous source (Word, HTML, Markdown, web) again on pages of the import area.
 *
 * Used when passages are hidden or shown and with text heights measured in the browser (Pretext),
 * so that the number of pages is exact.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_source extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'sourceid' => new external_value(PARAM_INT, 'Source id'),
            'hidden' => new external_value(PARAM_RAW, 'JSON list of hidden passage indexes, empty: unchanged', VALUE_DEFAULT, ''),
            'measure' => new external_value(PARAM_RAW, 'JSON of measured heights by passage index', VALUE_DEFAULT, ''),
            'measurekey' => new external_value(PARAM_ALPHANUMEXT, 'Fonts and settings of the measurement', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Set the source.
     *
     * @param int $cmid
     * @param int $sourceid
     * @param string $hidden
     * @param string $measure
     * @param string $measurekey
     * @return array
     */
    public static function execute(
        int $cmid,
        int $sourceid,
        string $hidden = '',
        string $measure = '',
        string $measurekey = ''
    ): array {
        global $DB;
        $params = self::validate_parameters(
            self::execute_parameters(),
            compact('cmid', 'sourceid', 'hidden', 'measure', 'measurekey')
        );
        $document = document::from_cmid($params['cmid']);
        self::validate_context($document->get_context());
        require_capability('mod/buchbinder:edit', $document->get_context());
        $source = $document->get_source($params['sourceid']);
        if ($params['hidden'] !== '') {
            $list = json_decode($params['hidden'], true);
            $list = is_array($list) ? array_values(array_unique(array_map('intval', $list))) : [];
            $DB->set_field('buchbinder_source', 'hiddenblocks', json_encode($list), ['id' => $source->id]);
        }
        $measure = self::clean_measure(json_decode($params['measure'], true));
        $pages = importer::set_source($document, (int)$source->id, $measure ?: null, $params['measurekey'] ?: null);
        return ['pages' => count($pages)];
    }

    /**
     * Whitelist the measurement.
     *
     * @param mixed $raw
     * @return array
     */
    protected static function clean_measure($raw): array {
        $clean = [];
        if (!is_array($raw)) {
            return $clean;
        }
        $number = fn($v) => max(0.0, min(10.0, (float)$v));
        foreach ($raw as $index => $m) {
            if (!is_array($m) || !isset($m['h'])) {
                continue;
            }
            $entry = ['h' => $number($m['h'])];
            if (isset($m['lh'])) {
                $entry['lh'] = max(0.001, $number($m['lh']));
            }
            if (isset($m['blh'])) {
                $entry['blh'] = max(0.001, $number($m['blh']));
            }
            if (isset($m['lines']) && is_array($m['lines'])) {
                $entry['lines'] = array_map(fn($v) => max(0, (int)$v), array_slice(array_values($m['lines']), 0, 5000));
            }
            if (isset($m['notes']) && is_array($m['notes'])) {
                $entry['notes'] = array_map($number, array_values($m['notes']));
            }
            $clean[(int)$index] = $entry;
        }
        return $clean;
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'pages' => new external_value(PARAM_INT, 'Number of pages in the import area'),
        ]);
    }
}
