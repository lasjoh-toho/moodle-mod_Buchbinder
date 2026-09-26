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
 * Layout desk: full screen DTP workspace showing the document as booklet (double pages).
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_buchbinder\local\booklet;
use mod_buchbinder\local\document;

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$pageid = optional_param('pageid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'buchbinder');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/buchbinder:edit', $context);

$document = document::from_cmid($cm->id);
$instance = $document->get_instance();
$baseurl = new moodle_url('/mod/buchbinder/desk.php', ['id' => $cm->id]);

// Page actions of the pages panel.
if ($action !== '') {
    require_sesskey();
    $target = $pageid;
    switch ($action) {
        case 'blankbefore':
        case 'blankafter':
            $target = (int)$document->insert_blank_page($pageid, $action === 'blankbefore')->id;
            break;
        case 'addpage':
            $target = (int)$document->add_canvas_page()->id;
            break;
        case 'moveup':
        case 'movedown':
            $document->move_page($pageid, $action === 'moveup' ? -1 : 1);
            break;
        case 'align':
            $document->align_spread($pageid);
            break;
        case 'deletepage':
            $positions = array_keys($document->get_positions());
            $index = array_search($pageid, $positions);
            $document->delete_page($pageid);
            $target = $positions[$index + 1] ?? ($positions[$index - 1] ?? 0);
            break;
    }
    redirect(new moodle_url($baseurl, ['pageid' => $target]));
}

$pages = array_values($document->get_pages());
$overlays = $document->get_overlays(array_map(fn($p) => (int)$p->id, $pages));
$firstright = $document->first_page_right();
$issues = [];
foreach ($document->layout_issues() as $issue) {
    $issues[$issue['leftpageid']] = true;
}

// Spreads of the whole document.
$items = [];
foreach ($pages as $i => $page) {
    $position = $i + 1;
    $items[] = [
        'id' => (int)$page->id,
        'position' => $position,
        'side' => booklet::side($position, $firstright),
        'landscape' => (bool)$page->landscapelock,
        'page' => $page,
    ];
}
$spreads = booklet::spreads($items, $firstright);
$current = 0;
foreach ($spreads as $index => $row) {
    foreach ($row as $item) {
        if ($item['id'] === $pageid) {
            $current = $index;
        }
    }
}

$act = fn($a, $pid) => (new moodle_url($baseurl, ['action' => $a, 'pageid' => $pid, 'sesskey' => sesskey()]))->out(false);
$panel = [];
foreach ($spreads as $index => $row) {
    $thumbs = [];
    foreach ($row as $item) {
        $page = $item['page'];
        $thumbs[] = [
            'id' => $item['id'],
            'position' => $item['position'],
            'side' => $item['side'],
            'thumb' => $page->pagetype === 'image' ? $document->page_image_url($page)->out(false) : '',
            'frames' => count(array_filter($overlays[$page->id], fn($o) => $o->overlaytype === 'textframe')),
            'blank' => $page->pagetype === 'canvas' && !$overlays[$page->id],
            'issue' => isset($issues[$item['id']]),
            'alignurl' => $act('align', $item['id']),
        ];
    }
    $panel[] = [
        'index' => $index,
        'current' => $index === $current,
        'url' => (new moodle_url($baseurl, ['pageid' => $row[0]['id']]))->out(false),
        'pages' => $thumbs,
        'label' => implode('–', array_map(fn($t) => $t['position'], $thumbs)),
        'double' => count($row) === 2,
        'single' => count($row) === 1 ? $row[0]['side'] : '',
    ];
}

// The current spread on the pasteboard.
$stages = [];
$config = ['cmid' => $cm->id, 'pages' => [], 'overlays' => [], 'glossaries' => [],
    'maxbytes' => document::max_bytes($context)];
foreach ($spreads[$current] ?? [] as $item) {
    $page = $item['page'];
    $guides = booklet::type_area($item['side']);
    $stages[] = [
        'id' => $item['id'],
        'position' => $item['position'],
        'side' => $item['side'],
        'sidelabel' => get_string('side_' . $item['side'], 'mod_buchbinder'),
        'isimage' => $page->pagetype === 'image',
        'imageurl' => $page->pagetype === 'image' ? $document->page_image_url($page)->out(false) : '',
        'html' => in_array($page->pagetype, ['html', 'layout']) ? $document->page_html($page) : '',
        'ratio' => $page->height ? round($page->height / max(1, $page->width) * 100, 4) : 141.4286,
        'guidestyle' => sprintf(
            'left:%.4f%%;top:%.4f%%;width:%.4f%%;height:%.4f%%;',
            $guides[0] * 100,
            $guides[1] * 100,
            $guides[2] * 100,
            $guides[3] * 100
        ),
        'actions' => [
            'blankbefore' => $act('blankbefore', $item['id']),
            'blankafter' => $act('blankafter', $item['id']),
            'moveup' => $act('moveup', $item['id']),
            'movedown' => $act('movedown', $item['id']),
            'delete' => $act('deletepage', $item['id']),
        ],
        'issue' => isset($issues[$item['id']]),
        'alignurl' => $act('align', $item['id']),
    ];
    $config['pages'][] = ['pageid' => $item['id'], 'side' => $item['side'], 'guides' => $guides];
    foreach ($overlays[$page->id] as $o) {
        $url = $o->overlaytype === 'imageframe' ? $document->frame_image_url($o) : $document->audio_url($o);
        $config['overlays'][] = ['id' => (int)$o->id, 'pageid' => (int)$o->pageid, 'type' => $o->overlaytype,
            'x' => (float)$o->x, 'y' => (float)$o->y, 'w' => (float)$o->w, 'h' => (float)$o->h,
            'settings' => $o->settings, 'audiourl' => $o->overlaytype === 'audio' && $url ? $url->out(false) : '',
            'imageurl' => $o->overlaytype === 'imageframe' && $url ? $url->out(false) : ''];
    }
}
foreach (get_all_instances_in_course('glossary', $course) as $g) {
    $config['glossaries'][] = ['id' => (int)$g->id, 'name' => format_string($g->name)];
}

$PAGE->set_url(new moodle_url($baseurl, ['pageid' => $pageid]));
$PAGE->set_title(format_string($instance->name) . ': ' . get_string('desk', 'mod_buchbinder'));
$PAGE->set_pagelayout('embedded');
$PAGE->add_body_class('bb-desk-body');
$PAGE->requires->js_call_amd('mod_buchbinder/editor', 'init', ['#buchbinder-editor']);

$studio = fn($tab) => (new moodle_url('/mod/buchbinder/studio.php', ['id' => $cm->id, 'tab' => $tab]))->out(false);
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_buchbinder/desk', [
    'name' => format_string($instance->name),
    'spreads' => $panel,
    'stages' => $stages,
    'hasstages' => !empty($stages),
    'double' => count($stages) === 2,
    'config' => json_encode($config),
    'hasglossaries' => !empty($config['glossaries']),
    'issues' => count($issues),
    'addpageurl' => $act('addpage', 0),
    'prevurl' => $current > 0 ? $panel[$current - 1]['url'] : '',
    'nexturl' => isset($panel[$current + 1]) ? $panel[$current + 1]['url'] : '',
    'links' => [
        'import' => $studio('import'),
        'pages' => $studio('pages'),
        'sources' => $studio('sources'),
        'publish' => $studio('publish'),
        'preview' => (new moodle_url('/mod/buchbinder/view.php', ['id' => $cm->id]))->out(false),
        'print' => (new moodle_url('/mod/buchbinder/print.php', ['id' => $cm->id]))->out(false),
        'layout' => (new moodle_url('/mod/buchbinder/layout.php', ['id' => $cm->id]))->out(false),
        'course' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
    ],
]);
echo $OUTPUT->footer();
