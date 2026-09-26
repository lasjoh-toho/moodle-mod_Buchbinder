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
 * Restore structure for mod_buchbinder.
 *
 * @package    mod_buchbinder
 * @category   backup
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_buchbinder_activity_structure_step extends restore_activity_structure_step {
    /**
     * Paths to restore.
     *
     * @return restore_path_element[]
     */
    protected function define_structure() {
        $paths = [
            new restore_path_element('buchbinder', '/activity/buchbinder'),
            new restore_path_element('buchbinder_source', '/activity/buchbinder/sources/source'),
            new restore_path_element('buchbinder_page', '/activity/buchbinder/pages/page'),
            new restore_path_element('buchbinder_overlay', '/activity/buchbinder/pages/page/overlays/overlay'),
        ];
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Activity record.
     *
     * @param array $data
     */
    protected function process_buchbinder($data) {
        global $DB;
        $data = (object)$data;
        $data->course = $this->get_courseid();
        // Keep the asset bank origin only if the master exists on this site.
        if (!empty($data->masterid) && !$DB->record_exists('buchbinder', ['id' => $data->masterid])) {
            $data->masterid = null;
            $data->masterrange = null;
        }
        $newid = $DB->insert_record('buchbinder', $data);
        $this->apply_activity_instance($newid);
    }

    /**
     * Source record.
     *
     * @param array $data
     */
    protected function process_buchbinder_source($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->buchbinderid = $this->get_new_parentid('buchbinder');
        $newid = $DB->insert_record('buchbinder_source', $data);
        $this->set_mapping('buchbinder_source', $oldid, $newid, true);
    }

    /**
     * Page record. Spread ids are remapped in after_execute().
     *
     * @param array $data
     */
    protected function process_buchbinder_page($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->buchbinderid = $this->get_new_parentid('buchbinder');
        $data->sourceid = $data->sourceid ? ($this->get_mappingid('buchbinder_source', $data->sourceid) ?: null) : null;
        $newid = $DB->insert_record('buchbinder_page', $data);
        $this->set_mapping('buchbinder_page', $oldid, $newid, true);
    }

    /**
     * Overlay record.
     *
     * @param array $data
     */
    protected function process_buchbinder_overlay($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->pageid = $this->get_new_parentid('buchbinder_page');
        $newid = $DB->insert_record('buchbinder_overlay', $data);
        $this->set_mapping('buchbinder_overlay', $oldid, $newid, true);
    }

    /**
     * Restore files and remap double page links.
     */
    protected function after_execute() {
        global $DB;
        $this->add_related_files('mod_buchbinder', 'intro', null);
        $this->add_related_files('mod_buchbinder', 'clips', null);
        $this->add_related_files('mod_buchbinder', 'source', 'buchbinder_source');
        $this->add_related_files('mod_buchbinder', 'page', 'buchbinder_page');
        $this->add_related_files('mod_buchbinder', 'pagecontent', 'buchbinder_page');
        $this->add_related_files('mod_buchbinder', 'audio', 'buchbinder_overlay');

        $instanceid = $this->task->get_activityid();
        $pages = $DB->get_records_select('buchbinder_page', 'buchbinderid = ? AND spreadid IS NOT NULL', [$instanceid]);
        foreach ($pages as $page) {
            $spreadid = $this->get_mappingid('buchbinder_page', $page->spreadid) ?: null;
            $DB->set_field('buchbinder_page', 'spreadid', $spreadid, ['id' => $page->id]);
            if (!$spreadid) {
                $DB->set_field('buchbinder_page', 'spreadside', null, ['id' => $page->id]);
            }
        }
    }
}
