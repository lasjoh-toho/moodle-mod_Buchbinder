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
 * Reader view with interactive layers, mobile reflow and column zoom.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_buchbinder\local\document;
use mod_buchbinder\local\overlay_types;

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'buchbinder');
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/buchbinder:view', $context);

$document = document::from_cmid($cm->id);
$instance = $document->get_instance();
buchbinder_view($instance, $course, $cm, $context);

$PAGE->set_url('/mod/buchbinder/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));

$canedit = has_capability('mod/buchbinder:edit', $context);
$cansolutions = has_capability('mod/buchbinder:viewsolutions', $context);
$pages = $document->get_published_pages();
$overlays = $document->get_overlays(array_keys($pages));
$sources = $document->get_sources();

$pct = fn($v) => round((float)$v * 100, 4);
$items = [];
$hasreflow = false;
foreach ($pages as $page) {
    $item = [
        'id' => $page->id,
        'number' => $page->pagenumber,
        'isimage' => $page->pagetype === 'image',
        'imageurl' => $page->pagetype === 'image' ? $document->page_image_url($page)->out(false) : '',
        'html' => $page->pagetype === 'html' ? $document->page_html($page) : '',
        'ratio' => $page->width ? round($page->height / $page->width * 100, 4) : 141.4286,
        'landscape' => $page->width > $page->height,
        'spreadside' => $page->spreadside,
        'overlays' => [],
        'reflow' => [],
        'citation' => '',
    ];
    $reflow = [];
    $columns = 0;
    foreach ($overlays[$page->id] as $o) {
        $s = $o->settings;
        $ov = [
            'id' => $o->id,
            'style' => "left:{$pct($o->x)}%;top:{$pct($o->y)}%;width:{$pct($o->w)}%;height:{$pct($o->h)}%;",
            'is' . $o->overlaytype => true,
        ];
        switch ($o->overlaytype) {
            case overlay_types::MASK:
                $ov += ['black' => $s['style'] === 'black', 'revealable' => $s['revealable'], 'label' => $s['label'],
                    'teacher' => $cansolutions];
                break;
            case overlay_types::TEXTBOX:
                $ov += ['text' => $s['text'], 'textstyle' => "--bb-fs:{$s['fontsize']};color:{$s['color']};" .
                    "background:{$s['bgcolor']};text-align:{$s['align']};" . ($s['bold'] ? 'font-weight:bold;' : '')];
                break;
            case overlay_types::AUDIO:
                $url = $document->audio_url($o);
                $ov += ['audiourl' => $url ? $url->out(false) : '', 'tts' => $s['mode'] === 'tts',
                    'ttstext' => $s['ttstext'], 'ttslang' => $s['ttslang'],
                    'label' => $s['label'] ?: get_string('overlay_audio', 'mod_buchbinder')];
                break;
            case overlay_types::GLOSSARY:
                $ov += ['term' => $s['term']];
                break;
            case overlay_types::COLUMN:
                $ov += ['column' => ++$columns];
                break;
            case overlay_types::REFLOW:
                $reflow[] = ['y' => (float)$o->y, 'x' => (float)$o->x, 'text' => $s['text'], 'role' => $s['role']];
                continue 2;
        }
        $item['overlays'][] = $ov;
    }
    // Reading order: columns left to right, top to bottom inside a column.
    usort($reflow, fn($a, $b) => [round($a['x'], 1), $a['y']] <=> [round($b['x'], 1), $b['y']]);
    foreach ($reflow as $block) {
        $item['reflow'][] = ['text' => $block['text'], 'is' . $block['role'] => true];
    }
    $item['hasreflow'] = !empty($item['reflow']) || $page->pagetype === 'html';
    $hasreflow = $hasreflow || !empty($item['reflow']);
    if ($page->sourceid && isset($sources[$page->sourceid]) && document::has_citation($sources[$page->sourceid])) {
        $item['citation'] = document::citation($sources[$page->sourceid]);
    }
    $items[] = $item;
}

$PAGE->requires->js_call_amd('mod_buchbinder/viewer', 'init', ['#buchbinder-viewer-' . $cm->id, $cm->id]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_buchbinder/view', [
    'cmid' => $cm->id,
    'pages' => $items,
    'haspages' => !empty($items),
    'reflow' => $instance->enablereflow && $hasreflow,
    'printurl' => $instance->enableprint && has_capability('mod/buchbinder:print', $context)
        ? (new moodle_url('/mod/buchbinder/print.php', ['id' => $cm->id]))->out(false) : '',
    'studiourl' => $canedit ? (new moodle_url('/mod/buchbinder/studio.php', ['id' => $cm->id]))->out(false) : '',
]);
echo $OUTPUT->footer();
