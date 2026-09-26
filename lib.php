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
 * Library of functions for mod_buchbinder.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Supported features.
 *
 * @param string $feature
 * @return mixed
 */
function buchbinder_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT;
        default:
            return null;
    }
}

/**
 * Add an instance.
 *
 * @param stdClass $data
 * @param mod_buchbinder_mod_form|null $mform
 * @return int
 */
function buchbinder_add_instance($data, $mform = null) {
    global $DB;
    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->pagerange = trim($data->pagerange ?? '');
    return $DB->insert_record('buchbinder', $data);
}

/**
 * Update an instance.
 *
 * @param stdClass $data
 * @param mod_buchbinder_mod_form|null $mform
 * @return bool
 */
function buchbinder_update_instance($data, $mform = null) {
    global $DB;
    $data->id = $data->instance;
    $data->timemodified = time();
    $data->pagerange = trim($data->pagerange ?? '');
    return $DB->update_record('buchbinder', $data);
}

/**
 * Delete an instance with all pages, overlays and files.
 *
 * @param int $id
 * @return bool
 */
function buchbinder_delete_instance($id) {
    global $DB;
    $instance = $DB->get_record('buchbinder', ['id' => $id]);
    if (!$instance) {
        return false;
    }
    $cm = get_coursemodule_from_instance('buchbinder', $id);
    if ($cm) {
        $document = new \mod_buchbinder\local\document($instance, context_module::instance($cm->id));
        $document->delete_all();
    }
    // Documents derived from this master keep their copies.
    $DB->set_field('buchbinder', 'masterid', null, ['masterid' => $id]);
    $DB->delete_records('buchbinder', ['id' => $id]);
    return true;
}

/**
 * Serve plugin files.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool false if not found
 */
function buchbinder_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }
    require_course_login($course, true, $cm);

    if ($filearea === 'source') {
        require_capability('mod/buchbinder:edit', $context);
    } else if (in_array($filearea, ['page', 'pagecontent', 'audio'])) {
        require_capability('mod/buchbinder:view', $context);
    } else {
        return false;
    }

    $itemid = (int)array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_buchbinder', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    // Pages outside the published excerpt are only visible to editors.
    if (in_array($filearea, ['page', 'pagecontent', 'audio']) && !has_capability('mod/buchbinder:edit', $context)) {
        global $DB;
        $pageid = $itemid;
        if ($filearea === 'audio') {
            $pageid = (int)$DB->get_field('buchbinder_overlay', 'pageid', ['id' => $itemid]);
        }
        $instance = $DB->get_record('buchbinder', ['id' => $cm->instance], '*', MUST_EXIST);
        $document = new \mod_buchbinder\local\document($instance, $context);
        if (!array_key_exists($pageid, $document->get_published_pages())) {
            return false;
        }
    }
    // Learners get page images with non revealable masks burned in.
    if ($filearea === 'page' && !has_capability('mod/buchbinder:viewsolutions', $context)) {
        global $DB;
        $instance = $DB->get_record('buchbinder', ['id' => $cm->instance], '*', MUST_EXIST);
        $document = new \mod_buchbinder\local\document($instance, $context);
        $file = $document->get_learner_page_file($document->get_page($itemid)) ?? $file;
    }
    // Page images are revisioned via the rev parameter, but may change in place.
    send_stored_file($file, 60, 0, $forcedownload, $options);
}

/**
 * Add the studio and print links to the activity navigation.
 *
 * @param settings_navigation $settings
 * @param navigation_node $node
 */
function buchbinder_extend_settings_navigation(settings_navigation $settings, navigation_node $node) {
    $cm = $settings->get_page()->cm;
    if (!$cm) {
        return;
    }
    $context = context_module::instance($cm->id);
    if (has_capability('mod/buchbinder:edit', $context)) {
        $node->add(
            get_string('studio', 'mod_buchbinder'),
            new moodle_url('/mod/buchbinder/studio.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'buchbinderstudio',
            new pix_icon('i/edit', '')
        );
    }
    if (has_capability('mod/buchbinder:print', $context)) {
        $node->add(
            get_string('ecoprint', 'mod_buchbinder'),
            new moodle_url('/mod/buchbinder/print.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'buchbinderprint',
            new pix_icon('t/print', '')
        );
    }
}

/**
 * Mark the activity as viewed (completion, events).
 *
 * @param stdClass $instance
 * @param stdClass $course
 * @param stdClass $cm
 * @param context_module $context
 */
function buchbinder_view($instance, $course, $cm, $context) {
    $event = \mod_buchbinder\event\course_module_viewed::create([
        'objectid' => $instance->id,
        'context' => $context,
    ]);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('buchbinder', $instance);
    $event->trigger();

    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}
