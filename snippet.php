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
 * Clipboard snippets and editing of html pages.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_buchbinder\local\document;
use mod_buchbinder\local\importer;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

$id = required_param('id', PARAM_INT);
$pageid = optional_param('pageid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'buchbinder');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/buchbinder:edit', $context);

$document = document::from_cmid($cm->id);
$page = $pageid ? $document->get_page($pageid) : null;
if ($page && $page->pagetype !== 'html') {
    throw new moodle_exception('errornothtml', 'mod_buchbinder');
}

$url = new moodle_url('/mod/buchbinder/snippet.php', ['id' => $cm->id, 'pageid' => $pageid]);
$returnurl = new moodle_url('/mod/buchbinder/studio.php', ['id' => $cm->id, 'tab' => 'pages']);
$PAGE->set_url($url);
$PAGE->set_title(get_string($page ? 'editpage' : 'clipboardsnippet', 'mod_buchbinder'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();

$editoroptions = ['maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes' => document::max_bytes($context),
    'context' => $context, 'noclean' => false, 'trusttext' => false];

$data = (object)['id' => $cm->id, 'pageid' => $pageid, 'content' => $page->content ?? '', 'contentformat' => FORMAT_HTML];
$data = file_prepare_standard_editor(
    $data,
    'content',
    $editoroptions,
    $context,
    'mod_buchbinder',
    'pagecontent',
    $page ? $page->id : null
);

$form = new \mod_buchbinder\form\snippet_form($url, ['editoroptions' => $editoroptions, 'editing' => (bool)$page]);
$form->set_data($data);

if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($formdata = $form->get_data()) {
    if (!$page) {
        $page = (new importer($document))->add_snippet('clipboard', '', [], [
            'title' => $formdata->title, 'author' => $formdata->author, 'url' => $formdata->url ?: null,
        ]);
    }
    $formdata = file_postupdate_standard_editor(
        $formdata,
        'content',
        $editoroptions,
        $context,
        'mod_buchbinder',
        'pagecontent',
        $page->id
    );
    $document->update_html_page($page->id, $formdata->content);
    redirect($returnurl, get_string('changessaved'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($page ? 'editpage' : 'clipboardsnippet', 'mod_buchbinder'));
if (!$page) {
    echo html_writer::div(get_string('clipboardsnippet_help', 'mod_buchbinder'), 'mb-3');
}
$form->display();
echo $OUTPUT->footer();
