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

/**
 * Restore task for mod_buchbinder.
 *
 * @package    mod_buchbinder
 * @category   backup
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/buchbinder/backup/moodle2/restore_buchbinder_stepslib.php');

/**
 * Restore task for mod_buchbinder.
 */
class restore_buchbinder_activity_task extends restore_activity_task {
    /**
     * No specific settings.
     */
    protected function define_my_settings() {
    }

    /**
     * Structure step.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_buchbinder_activity_structure_step('buchbinder_structure', 'buchbinder.xml'));
    }

    /**
     * Contents that may contain encoded links.
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents() {
        return [
            new restore_decode_content('buchbinder', ['intro'], 'buchbinder'),
            new restore_decode_content('buchbinder_page', ['content'], 'buchbinder_page'),
        ];
    }

    /**
     * Link decoding rules.
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('BUCHBINDERVIEWBYID', '/mod/buchbinder/view.php?id=$1', 'course_module'),
            new restore_decode_rule('BUCHBINDERINDEX', '/mod/buchbinder/index.php?id=$1', 'course'),
        ];
    }

    /**
     * Log rules.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules() {
        return [
            new restore_log_rule('buchbinder', 'view', 'view.php?id={course_module}', '{buchbinder}'),
        ];
    }

    /**
     * Course level log rules.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules_for_course() {
        return [
            new restore_log_rule('buchbinder', 'view all', 'index.php?id={course}', null),
        ];
    }

    /**
     * Glossary overlays point to glossaries of the course. Once all activities are
     * restored, map them to the restored glossaries (or keep them if the glossary is
     * not part of the restore, e.g. when duplicating an activity in the same course).
     */
    public function after_restore() {
        global $DB;
        $instanceid = $this->get_activityid();
        $courseid = $this->get_courseid();
        $sql = "SELECT o.id, o.data
                  FROM {buchbinder_overlay} o
                  JOIN {buchbinder_page} p ON p.id = o.pageid
                 WHERE p.buchbinderid = ? AND o.overlaytype = ?";
        foreach ($DB->get_records_sql($sql, [$instanceid, 'glossary']) as $overlay) {
            $settings = json_decode($overlay->data ?? '', true) ?: [];
            $old = (int)($settings['glossaryid'] ?? 0);
            if (!$old) {
                continue;
            }
            $mapping = restore_dbops::get_backup_ids_record($this->get_restoreid(), 'glossary', $old);
            $new = $mapping ? (int)$mapping->newitemid : 0;
            if (!$new && !$DB->record_exists('glossary', ['id' => $old, 'course' => $courseid])) {
                $new = 0;
            } else if (!$new) {
                $new = $old;
            }
            if ($new != $old) {
                $settings['glossaryid'] = (int)$new;
                $DB->set_field('buchbinder_overlay', 'data', json_encode($settings), ['id' => $overlay->id]);
            }
        }
    }
}
