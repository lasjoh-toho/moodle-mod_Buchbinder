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
 * Backup structure for mod_buchbinder.
 *
 * @package    mod_buchbinder
 * @category   backup
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_buchbinder_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define the structure: activity, sources, pages with overlays.
     *
     * The document is teaching material only, there is no user data.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $buchbinder = new backup_nested_element('buchbinder', ['id'], [
            'name', 'intro', 'introformat', 'pagerange', 'enablereflow', 'enableprint', 'firstpageright', 'ismaster', 'masterid',
            'masterrange', 'timecreated', 'timemodified']);

        $sources = new backup_nested_element('sources');
        $source = new backup_nested_element('source', ['id'], [
            'sourcetype', 'title', 'url', 'author', 'filename', 'timeaccessed', 'showcitation', 'timecreated']);

        $pages = new backup_nested_element('pages');
        $page = new backup_nested_element('page', ['id'], [
            'sourceid', 'sortorder', 'pagetype', 'content', 'contentformat', 'width', 'height', 'landscapelock',
            'spreadid', 'spreadside', 'pinside', 'filler', 'pagestyle', 'timemodified']);

        $overlays = new backup_nested_element('overlays');
        $overlay = new backup_nested_element('overlay', ['id'], [
            'overlaytype', 'x', 'y', 'w', 'h', 'data', 'sortorder', 'timemodified']);

        $buchbinder->add_child($sources);
        $sources->add_child($source);
        $buchbinder->add_child($pages);
        $pages->add_child($page);
        $page->add_child($overlays);
        $overlays->add_child($overlay);

        $buchbinder->set_source_table('buchbinder', ['id' => backup::VAR_ACTIVITYID]);
        $source->set_source_table('buchbinder_source', ['buchbinderid' => backup::VAR_PARENTID], 'id ASC');
        $page->set_source_table('buchbinder_page', ['buchbinderid' => backup::VAR_PARENTID], 'sortorder ASC');
        $overlay->set_source_table('buchbinder_overlay', ['pageid' => backup::VAR_PARENTID], 'sortorder ASC');

        $buchbinder->annotate_files('mod_buchbinder', 'intro', null);
        $buchbinder->annotate_files('mod_buchbinder', 'clips', null);
        $source->annotate_files('mod_buchbinder', 'source', 'id');
        $page->annotate_files('mod_buchbinder', 'page', 'id');
        $page->annotate_files('mod_buchbinder', 'pagecontent', 'id');
        $overlay->annotate_files('mod_buchbinder', 'audio', 'id');
        $overlay->annotate_files('mod_buchbinder', 'frameimage', 'id');

        return $this->prepare_activity_structure($buchbinder);
    }
}
