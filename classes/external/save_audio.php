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
 * Attach a recorded or uploaded audio file to an audio overlay.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_audio extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'overlayid' => new external_value(PARAM_INT, 'Overlay id'),
            'filename' => new external_value(PARAM_FILE, 'File name'),
            'content' => new external_value(PARAM_RAW, 'Base64 encoded content'),
        ]);
    }

    /**
     * Store the audio.
     *
     * @param int $cmid
     * @param int $overlayid
     * @param string $filename
     * @param string $content
     * @return array
     */
    public static function execute(int $cmid, int $overlayid, string $filename, string $content): array {
        $params = self::validate_parameters(self::execute_parameters(), compact('cmid', 'overlayid', 'filename', 'content'));
        $document = document::from_cmid($params['cmid']);
        self::validate_context($document->get_context());
        require_capability('mod/buchbinder:edit', $document->get_context());
        $binary = base64_decode($params['content'], true);
        if ($binary === false || $binary === '') {
            throw new \invalid_parameter_exception('Invalid audio content');
        }
        $overlay = $document->save_audio($params['overlayid'], $params['filename'], $binary);
        return ['url' => $document->audio_url($overlay)->out(false), 'settings' => json_encode($overlay->settings)];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'url' => new external_value(PARAM_URL, 'Audio URL'),
            'settings' => new external_value(PARAM_RAW, 'JSON encoded settings'),
        ]);
    }
}
