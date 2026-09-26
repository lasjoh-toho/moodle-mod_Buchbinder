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
 * Compose pages with the layout system (Quarto flavoured Markdown).
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_buchbinder\local\document;
use mod_buchbinder\local\layout_renderer;

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$pageid = optional_param('pageid', 0, PARAM_INT);
$template = optional_param('template', '', PARAM_ALPHA);
$deleteclip = optional_param('deleteclip', '', PARAM_FILE);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'buchbinder');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/buchbinder:edit', $context);

$document = document::from_cmid($cm->id);
$page = $pageid ? $document->get_page($pageid) : null;
if ($page && $page->pagetype !== 'layout') {
    throw new moodle_exception('errornotlayout', 'mod_buchbinder');
}

$url = new moodle_url('/mod/buchbinder/layout.php', ['id' => $cm->id, 'pageid' => $pageid, 'template' => $template]);
$returnurl = new moodle_url('/mod/buchbinder/studio.php', ['id' => $cm->id, 'tab' => 'pages']);
$PAGE->set_url($url);
$PAGE->set_title(get_string('layoutpage', 'mod_buchbinder'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();

if ($deleteclip !== '') {
    require_sesskey();
    $document->delete_clip($deleteclip);
    redirect($url);
}

// New page without template: choose one first.
if (!$page && $template === '') {
    $templates = [];
    foreach (layout_renderer::TEMPLATES as $t) {
        $templates[] = [
            'name' => get_string('layouttemplate_' . $t, 'mod_buchbinder'),
            'description' => get_string('layouttemplate_' . $t . '_desc', 'mod_buchbinder'),
            'url' => (new moodle_url($url, ['template' => $t]))->out(false),
        ];
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('newlayoutpage', 'mod_buchbinder'));
    echo $OUTPUT->render_from_template('mod_buchbinder/layout_templates', ['templates' => $templates]);
    echo $OUTPUT->footer();
    exit;
}

$form = new \mod_buchbinder\form\layout_form($url);
$form->set_data([
    'id' => $cm->id,
    'pageid' => $pageid,
    'template' => $template,
    'source' => $page ? $page->content : layout_renderer::template_source($template),
]);

if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    if ($page) {
        $n = $document->update_layout_page($page->id, $data->source);
    } else {
        $n = count($document->add_layout_pages($data->source));
    }
    redirect($returnurl, get_string('layoutsaved', 'mod_buchbinder', $n));
}

// Preview of the submitted (not yet saved) source.
$preview = '';
if (optional_param('previewbutton', false, PARAM_BOOL) && confirm_sesskey()) {
    $source = optional_param('source', '', PARAM_RAW);
    $pages = [];
    foreach (layout_renderer::split_pages($source) as $part) {
        $renderer = new layout_renderer(fn($src) => $document->clip_exists($src) ? $document->clip_url($src)->out(false) : null);
        $pages[] = ['html' => format_text($renderer->render($part), FORMAT_HTML, ['context' => $context, 'noclean' => true])];
    }
    $preview = $OUTPUT->render_from_template('mod_buchbinder/layout_preview', ['pages' => $pages]);
}

$clips = [];
foreach ($document->get_clips() as $filename => $file) {
    $clips[] = [
        'filename' => $filename,
        'url' => $document->clip_url($filename)->out(false),
        'markdown' => '![](' . $filename . ')',
        'deleteurl' => (new moodle_url($url, ['deleteclip' => $filename, 'sesskey' => sesskey()]))->out(false),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($page ? 'editlayoutpage' : 'newlayoutpage', 'mod_buchbinder'));
echo $OUTPUT->render_from_template('mod_buchbinder/layout_editor', [
    'form' => $form->render(),
    'preview' => $preview,
    'cheatsheet' => get_string('layoutsyntax_help', 'mod_buchbinder'),
    'clips' => $clips,
    'hasclips' => !empty($clips),
    'canvasurl' => (new moodle_url('/mod/buchbinder/studio.php', ['id' => $cm->id, 'tab' => 'canvas']))->out(false),
]);
echo $OUTPUT->footer();
