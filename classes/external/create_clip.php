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

/**
 * Cut a region out of an image page for use in composed pages.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_clip extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'pageid' => new external_value(PARAM_INT, 'Page id'),
            'x' => new external_value(PARAM_FLOAT, 'Left, relative 0..1'),
            'y' => new external_value(PARAM_FLOAT, 'Top, relative 0..1'),
            'w' => new external_value(PARAM_FLOAT, 'Width, relative 0..1'),
            'h' => new external_value(PARAM_FLOAT, 'Height, relative 0..1'),
        ]);
    }

    /**
     * Create the clip.
     *
     * @param int $cmid
     * @param int $pageid
     * @param float $x
     * @param float $y
     * @param float $w
     * @param float $h
     * @return array
     */
    public static function execute(int $cmid, int $pageid, float $x, float $y, float $w, float $h): array {
        $params = self::validate_parameters(self::execute_parameters(), compact('cmid', 'pageid', 'x', 'y', 'w', 'h'));
        $document = document::from_cmid($params['cmid']);
        self::validate_context($document->get_context());
        require_capability('mod/buchbinder:edit', $document->get_context());
        $filename = $document->create_clip($params['pageid'], [$params['x'], $params['y'], $params['w'], $params['h']]);
        return ['filename' => $filename, 'url' => $document->clip_url($filename)->out(false),
            'markdown' => '![](' . $filename . ')'];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'filename' => new external_value(PARAM_FILE, 'File name of the clip'),
            'url' => new external_value(PARAM_URL, 'URL of the clip'),
            'markdown' => new external_value(PARAM_RAW, 'Markdown to insert the clip into a layout'),
        ]);
    }
}
