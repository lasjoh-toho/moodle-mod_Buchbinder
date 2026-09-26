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
 * Site administration settings for mod_buchbinder.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    global $CFG;

    // Maximum file size for imports and audio files.
    $sizes = get_max_upload_sizes($CFG->maxbytes);
    $settings->add(new admin_setting_configselect(
        'buchbinder/maxbytes',
        new lang_string('maxbytes', 'mod_buchbinder'),
        new lang_string('maxbytes_desc', 'mod_buchbinder'),
        0,
        $sizes
    ));

    $settings->add(new admin_setting_configtext(
        'buchbinder/maxpages',
        new lang_string('maxpages', 'mod_buchbinder'),
        new lang_string('maxpages_desc', 'mod_buchbinder'),
        300,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configselect(
        'buchbinder/rasterdpi',
        new lang_string('rasterdpi', 'mod_buchbinder'),
        new lang_string('rasterdpi_desc', 'mod_buchbinder'),
        150,
        [96 => '96', 120 => '120', 150 => '150', 200 => '200', 300 => '300']
    ));

    // Content harvester (server side proxy for web snippets).
    $settings->add(new admin_setting_heading(
        'buchbinder/harvesterheading',
        new lang_string('harvester', 'mod_buchbinder'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'buchbinder/enableharvester',
        new lang_string('enableharvester', 'mod_buchbinder'),
        new lang_string('enableharvester_desc', 'mod_buchbinder'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'buchbinder/harvestimages',
        new lang_string('harvestimages', 'mod_buchbinder'),
        new lang_string('harvestimages_desc', 'mod_buchbinder'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'buchbinder/harvestertimeout',
        new lang_string('harvestertimeout', 'mod_buchbinder'),
        new lang_string('harvestertimeout_desc', 'mod_buchbinder'),
        15,
        PARAM_INT
    ));
}
