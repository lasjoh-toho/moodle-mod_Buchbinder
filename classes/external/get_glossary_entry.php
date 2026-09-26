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
 * Resolve the definition of a glossary overlay (Moodle glossary API).
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_glossary_entry extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'overlayid' => new external_value(PARAM_INT, 'Overlay id'),
        ]);
    }

    /**
     * Look up the entry.
     *
     * @param int $cmid
     * @param int $overlayid
     * @return array
     */
    public static function execute(int $cmid, int $overlayid): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(), compact('cmid', 'overlayid'));
        $document = document::from_cmid($params['cmid']);
        self::validate_context($document->get_context());
        require_capability('mod/buchbinder:view', $document->get_context());

        $overlay = $document->get_overlay($params['overlayid']);
        $settings = $overlay->settings;
        $result = ['found' => false, 'concept' => $settings['term'] ?? '', 'definition' => '', 'url' => ''];
        if ($overlay->overlaytype !== 'glossary' || empty($settings['glossaryid']) || $settings['term'] === '') {
            return $result;
        }
        $glossary = $DB->get_record('glossary', ['id' => $settings['glossaryid'],
            'course' => $document->get_instance()->course]);
        if (!$glossary) {
            return $result;
        }
        $cm = get_coursemodule_from_instance('glossary', $glossary->id, $glossary->course);
        $glossarycontext = \context_module::instance($cm->id);
        $modinfo = get_fast_modinfo($glossary->course);
        if (!$modinfo->get_cm($cm->id)->uservisible || !has_capability('mod/glossary:view', $glossarycontext)) {
            return $result;
        }
        // Concept or alias, approved entries only.
        $sql = "SELECT e.*
                  FROM {glossary_entries} e
                 WHERE e.glossaryid = :glossaryid AND e.approved = 1
                   AND (" . $DB->sql_like('e.concept', ':concept', false) . "
                        OR EXISTS (SELECT 1 FROM {glossary_alias} a
                                    WHERE a.entryid = e.id AND " . $DB->sql_like('a.alias', ':alias', false) . "))
              ORDER BY e.id ASC";
        $entries = $DB->get_records_sql($sql, ['glossaryid' => $glossary->id,
            'concept' => $DB->sql_like_escape($settings['term']), 'alias' => $DB->sql_like_escape($settings['term'])], 0, 1);
        $entry = reset($entries);
        if (!$entry) {
            return $result;
        }
        $definition = file_rewrite_pluginfile_urls(
            $entry->definition,
            'pluginfile.php',
            $glossarycontext->id,
            'mod_glossary',
            'entry',
            $entry->id
        );
        return [
            'found' => true,
            'concept' => format_string($entry->concept, true, ['context' => $glossarycontext]),
            'definition' => format_text($definition, $entry->definitionformat, ['context' => $glossarycontext]),
            'url' => (new \moodle_url('/mod/glossary/showentry.php', ['eid' => $entry->id]))->out(false),
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'found' => new external_value(PARAM_BOOL, 'Whether an entry was found'),
            'concept' => new external_value(PARAM_TEXT, 'Concept'),
            'definition' => new external_value(PARAM_RAW, 'Formatted definition'),
            'url' => new external_value(PARAM_RAW, 'Link to the entry'),
        ]);
    }
}
