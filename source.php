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
 * Edit the provenance data (citation) of a source.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_buchbinder\local\document;

require_once(__DIR__ . '/../../config.php');

$cmid = required_param('cmid', PARAM_INT);
$id = required_param('id', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'buchbinder');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/buchbinder:edit', $context);

$document = document::from_cmid($cm->id);
$source = $DB->get_record('buchbinder_source', ['id' => $id, 'buchbinderid' => $cm->instance], '*', MUST_EXIST);

$url = new moodle_url('/mod/buchbinder/source.php', ['cmid' => $cm->id, 'id' => $id]);
$returnurl = new moodle_url('/mod/buchbinder/studio.php', ['id' => $cm->id, 'tab' => 'sources']);
$PAGE->set_url($url);
$PAGE->set_title(get_string('editsource', 'mod_buchbinder'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();

$delete = optional_param('delete', '', PARAM_ALPHA);
if ($delete !== '') {
    $pagecount = $DB->count_records('buchbinder_page', ['buchbinderid' => $cm->instance, 'sourceid' => $source->id]);
    if (in_array($delete, ['withpages', 'keeppages']) && confirm_sesskey()) {
        $deleted = $document->delete_source($source->id, $delete === 'withpages');
        redirect($returnurl, get_string('sourcedeleted', 'mod_buchbinder', $deleted));
    }
    $params = ['cmid' => $cm->id, 'id' => $source->id, 'sesskey' => sesskey()];
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('deletesource', 'mod_buchbinder'));
    echo $OUTPUT->box(get_string('deletesource_confirm', 'mod_buchbinder', (object)[
        'title' => format_string($source->title ?: $source->filename), 'pages' => $pagecount]), 'generalbox mb-3');
    echo html_writer::start_div('d-flex flex-wrap');
    if ($pagecount) {
        echo $OUTPUT->single_button(
            new moodle_url('/mod/buchbinder/source.php', $params + ['delete' => 'withpages']),
            get_string('deletesource_withpages', 'mod_buchbinder', $pagecount),
            'post',
            ['type' => 'danger']
        );
    }
    echo $OUTPUT->single_button(
        new moodle_url('/mod/buchbinder/source.php', $params + ['delete' => 'keeppages']),
        get_string($pagecount ? 'deletesource_keeppages' : 'delete', $pagecount ? 'mod_buchbinder' : 'moodle'),
        'post'
    );
    echo $OUTPUT->single_button($returnurl, get_string('cancel'), 'get');
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

$form = new \mod_buchbinder\form\source_form($url);
$form->set_data(['cmid' => $cm->id] + (array)$source);
if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    $data->url = $data->url ?: null;
    $document->update_source($data);
    redirect($returnurl, get_string('changessaved'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editsource', 'mod_buchbinder'));
$form->display();
echo $OUTPUT->footer();
