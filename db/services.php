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
 * External functions for mod_buchbinder.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_buchbinder_save_overlay' => [
        'classname' => \mod_buchbinder\external\save_overlay::class,
        'description' => 'Create or update an overlay on a page.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/buchbinder:edit',
    ],
    'mod_buchbinder_delete_overlay' => [
        'classname' => \mod_buchbinder\external\delete_overlay::class,
        'description' => 'Delete an overlay.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/buchbinder:edit',
    ],
    'mod_buchbinder_save_audio' => [
        'classname' => \mod_buchbinder\external\save_audio::class,
        'description' => 'Attach a recorded or uploaded audio file to an audio overlay.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/buchbinder:edit',
    ],
    'mod_buchbinder_get_glossary_entry' => [
        'classname' => \mod_buchbinder\external\get_glossary_entry::class,
        'description' => 'Resolve the glossary definition for a glossary overlay.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/buchbinder:view',
    ],
    'mod_buchbinder_get_import_jobs' => [
        'classname' => \mod_buchbinder\external\get_import_jobs::class,
        'description' => 'Status of background imports.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/buchbinder:edit',
    ],
    'mod_buchbinder_create_clip' => [
        'classname' => \mod_buchbinder\external\create_clip::class,
        'description' => 'Cut a region out of an image page for composed pages.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/buchbinder:edit',
    ],
    'mod_buchbinder_set_source' => [
        'classname' => \mod_buchbinder\external\set_source::class,
        'description' => 'Set a continuous source again in the import area (hidden passages, measured text).',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/buchbinder:edit',
    ],
    'mod_buchbinder_save_frame_image' => [
        'classname' => \mod_buchbinder\external\save_frame_image::class,
        'description' => 'Store the image of an image frame.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/buchbinder:edit',
    ],
];
