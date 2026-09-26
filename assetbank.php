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
 * Asset bank: reuse excerpts of master documents across courses.
 *
 * Masters are Buchbinder activities marked as "master document" in courses in which
 * the current user may edit Buchbinder activities. Pages are copied (including
 * overlays and audio) and the origin is tracked.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_buchbinder\local\document;
use mod_buchbinder\local\page_range;

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$masterid = optional_param('masterid', 0, PARAM_INT);
$range = optional_param('range', '', PARAM_TEXT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'buchbinder');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/buchbinder:edit', $context);
require_capability('mod/buchbinder:useassetbank', $context);

$url = new moodle_url('/mod/buchbinder/assetbank.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_title(get_string('assetbank', 'mod_buchbinder'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();

/**
 * Masters the user may copy from, keyed by buchbinder id.
 *
 * @return array [buchbinder record, cm]
 */
function mod_buchbinder_accessible_masters(int $excludeid): array {
    global $DB;
    $result = [];
    foreach ($DB->get_records_select('buchbinder', 'ismaster = 1 AND id <> ?', [$excludeid], 'name') as $master) {
        $mcm = get_coursemodule_from_instance('buchbinder', $master->id, $master->course);
        if (!$mcm) {
            continue;
        }
        if (has_capability('mod/buchbinder:edit', context_module::instance($mcm->id))) {
            $result[$master->id] = [$master, $mcm];
        }
    }
    return $result;
}

$masters = mod_buchbinder_accessible_masters($cm->instance);

if ($masterid && confirm_sesskey()) {
    if (!isset($masters[$masterid])) {
        throw new required_capability_exception($context, 'mod/buchbinder:edit', 'nopermissions', '');
    }
    if (!page_range::is_valid($range)) {
        redirect($url, get_string('errorpagerange', 'mod_buchbinder'), null, \core\output\notification::NOTIFY_ERROR);
    }
    [$master, $mcm] = $masters[$masterid];
    $target = document::from_cmid($cm->id);
    $n = $target->copy_pages_from(new document($master, context_module::instance($mcm->id)), trim($range));
    redirect(
        new moodle_url('/mod/buchbinder/studio.php', ['id' => $cm->id, 'tab' => 'pages']),
        get_string('pagesimported', 'mod_buchbinder', $n)
    );
}

$rows = [];
foreach ($masters as [$master, $mcm]) {
    $pagecount = $DB->count_records('buchbinder_page', ['buchbinderid' => $master->id]);
    $mcourse = get_course($master->course);
    $rows[] = [
        'id' => $master->id,
        'name' => format_string($master->name),
        'course' => format_string($mcourse->fullname),
        'pages' => $pagecount,
        'usage' => $DB->count_records('buchbinder', ['masterid' => $master->id]),
        'viewurl' => (new moodle_url('/mod/buchbinder/view.php', ['id' => $mcm->id]))->out(false),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('assetbank', 'mod_buchbinder'));
echo html_writer::div(get_string('assetbank_help', 'mod_buchbinder'), 'mb-3');
echo $OUTPUT->render_from_template('mod_buchbinder/assetbank', [
    'masters' => $rows,
    'hasmasters' => !empty($rows),
    'actionurl' => $url->out(false),
    'cmid' => $cm->id,
    'sesskey' => sesskey(),
]);
echo $OUTPUT->footer();
