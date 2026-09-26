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
 * Backup task for mod_buchbinder.
 *
 * @package    mod_buchbinder
 * @category   backup
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/buchbinder/backup/moodle2/backup_buchbinder_stepslib.php');

/**
 * Backup task for mod_buchbinder.
 */
class backup_buchbinder_activity_task extends backup_activity_task {
    /**
     * No specific settings.
     */
    protected function define_my_settings() {
    }

    /**
     * Structure step.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_buchbinder_activity_structure_step('buchbinder_structure', 'buchbinder.xml'));
    }

    /**
     * Encode links to the activity.
     *
     * @param string $content
     * @return string
     */
    public static function encode_content_links($content) {
        global $CFG;
        $base = preg_quote($CFG->wwwroot . '/mod/buchbinder', '#');
        $content = preg_replace('#(' . $base . '/index\.php\?id=)([0-9]+)#', '$@BUCHBINDERINDEX*$2@$', $content);
        $content = preg_replace('#(' . $base . '/view\.php\?id=)([0-9]+)#', '$@BUCHBINDERVIEWBYID*$2@$', $content);
        return $content;
    }
}
