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
 * Import desk: full screen workspace for imported material.
 *
 * Imports land in the import area. Their pages are shown as miniatures; a range of pages is taken
 * into the document. Pages can be split, chained and pinned to a side here, and passages of
 * continuous sources (Word, HTML, Markdown, web) can be hidden before they are set.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_buchbinder\local\booklet;
use mod_buchbinder\local\document;
use mod_buchbinder\local\flow;
use mod_buchbinder\local\harvester;
use mod_buchbinder\local\import_queue;
use mod_buchbinder\local\importer;
use mod_buchbinder\local\page_range;
use mod_buchbinder\local\thumbs;

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$sourceid = optional_param('sourceid', 0, PARAM_INT);
$pageid = optional_param('pageid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'buchbinder');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/buchbinder:edit', $context);

$document = document::from_cmid($cm->id);
$instance = $document->get_instance();
$baseurl = new moodle_url('/mod/buchbinder/import.php', ['id' => $cm->id]);
$sources = $document->get_sources();
if ($sourceid && !isset($sources[$sourceid])) {
    $sourceid = 0;
}
$here = new moodle_url($baseurl, ['sourceid' => $sourceid]);

// Actions.
if ($action !== '') {
    require_sesskey();
    $message = '';
    switch ($action) {
        case 'adopt':
            $staged = array_values($document->get_staged_pages($sourceid));
            $range = optional_param('range', '', PARAM_TEXT);
            $numbers = page_range::parse(trim($range) === '' ? '1-' . count($staged) : $range, count($staged));
            $ids = array_map(fn($n) => (int)$staged[$n - 1]->id, $numbers);
            $after = optional_param('after', -1, PARAM_INT);
            $count = $document->adopt_pages($ids, $after < 0 ? null : $after);
            $message = get_string('pagesadopted', 'mod_buchbinder', $count);
            break;
        case 'split':
            $document->split_page($pageid);
            break;
        case 'splitall':
            foreach ($document->get_staged_pages($sourceid) as $page) {
                if ($page->pagetype === 'image' && !$page->spreadid && $page->width > $page->height) {
                    $document->split_page((int)$page->id);
                }
            }
            break;
        case 'link':
            $document->link_spread($pageid);
            break;
        case 'unlink':
            $document->unlink_spread($pageid);
            break;
        case 'pinleft':
        case 'pinright':
            $document->set_pinside($pageid, $action === 'pinleft' ? booklet::LEFT : booklet::RIGHT);
            break;
        case 'unpin':
            $document->set_pinside($pageid, null);
            break;
        case 'deletepage':
            if ($document->get_page($pageid)->staged) {
                $document->delete_page($pageid, false);
            }
            break;
        case 'deletesource':
            $document->delete_source($sourceid, false);
            $here = $baseurl;
            break;
        case 'restyle':
            $style = optional_param('pagestyle', booklet::STYLE_STANDARD, PARAM_ALPHA);
            $DB->update_record('buchbinder_source', (object)['id' => $sourceid,
                'pagestyle' => in_array($style, booklet::styles(), true) ? $style : booklet::STYLE_STANDARD,
                'startright' => optional_param('startright', 0, PARAM_BOOL)]);
            importer::set_source($document, $sourceid);
            break;
        case 'dismissjob':
            import_queue::dismiss($document, required_param('jobid', PARAM_INT));
            break;
    }
    redirect($here, $message ?: null, null, \core\output\notification::NOTIFY_SUCCESS);
}

// Import forms (shown in the import dialog).
$maxbytes = document::max_bytes($context);
$formurl = new moodle_url($baseurl);
$importform = new \mod_buchbinder\form\import_form($formurl, ['maxbytes' => $maxbytes]);
$blankform = new \mod_buchbinder\form\blank_form($formurl);
$webform = null;
if (harvester::is_enabled() && has_capability('mod/buchbinder:harvest', $context)) {
    $webform = new \mod_buchbinder\form\web_form($formurl);
}
$form = optional_param('form', '', PARAM_ALPHA);
// Reopen the import dialog when a submitted form has errors.
$showimport = $form !== '' || data_submitted();
if ($form === 'blank' && ($data = $blankform->get_data())) {
    $importer = new importer($document, ['stage']);
    $importer->add_blank_pages($data->template, (int)$data->count, !empty($data->landscape));
    $newest = array_key_last($document->get_sources());
    redirect(new moodle_url($baseurl, ['sourceid' => $newest]));
}
if ($form === 'web' && $webform && ($data = $webform->get_data())) {
    try {
        $result = harvester::harvest($data->url, $data->selector ?? '', $maxbytes);
        $importer = new importer($document, ['stage', $data->pagestyle ?? booklet::STYLE_STANDARD]);
        $page = $importer->add_snippet('web', $result['html'], $result['images'], [
            'title' => $result['title'], 'url' => $result['url'], 'author' => $data->author ?? '',
        ]);
        redirect(new moodle_url($baseurl, ['sourceid' => $page->sourceid]), get_string('snippetharvested', 'mod_buchbinder'));
    } catch (moodle_exception $e) {
        \core\notification::error($e->getMessage());
    }
}
if ($form === '' && ($data = $importform->get_data())) {
    $draftid = file_get_submitted_draft_itemid('files');
    $fs = get_file_storage();
    $usercontext = context_user::instance($USER->id);
    $ops = array_keys(array_filter(['stage' => true, 'chop' => $data->chop, 'deskew' => $data->deskew,
        'shadow' => $data->shadow, 'split' => $data->split, 'startright' => $data->startright,
        'tufte' => ($data->pagestyle ?? '') === booklet::STYLE_TUFTE]));
    $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftid, 'sortorder, filename', false);
    $job = import_queue::enqueue($document, array_values($files), $ops, ['title' => $data->title,
        'author' => $data->author, 'url' => $data->url ?: null]);
    $fs->delete_area_files($usercontext->id, 'user', 'draft', $draftid);
    if ($job->status === import_queue::STATUS_QUEUED) {
        redirect($baseurl, get_string('importqueued', 'mod_buchbinder'));
    }
    if ($job->message) {
        \core\notification::error(nl2br(s($job->message)));
    }
    $sources = $document->get_sources();
    redirect(new moodle_url($baseurl, ['sourceid' => array_key_last($sources)]));
}
$forms = '';
foreach ([$importform, $blankform, $webform] as $f) {
    if ($f) {
        $f->set_data(['id' => $cm->id]);
        $forms .= $f->render();
    }
}

// Sources and their pages in the import area.
$staged = $document->get_staged_pages();
$bysource = [];
foreach ($staged as $page) {
    $bysource[(int)$page->sourceid][] = $page;
}
if (!$sourceid) {
    // The newest source with pages in the import area, otherwise the newest source.
    foreach (array_reverse($sources, true) as $sid => $source) {
        if (!empty($bysource[$sid])) {
            $sourceid = $sid;
            break;
        }
    }
    $sourceid = $sourceid ?: (int)array_key_last($sources);
}
$docpages = $document->get_pages();
$indocument = [];
foreach ($docpages as $page) {
    if ($page->sourceid) {
        $indocument[$page->sourceid] = ($indocument[$page->sourceid] ?? 0) + 1;
    }
}
$sourcelist = [];
foreach (array_reverse($sources, true) as $sid => $source) {
    $sourcelist[] = [
        'id' => $sid,
        'title' => format_string($source->title ?: ($source->filename ?: get_string(
            'sourcetype_' . $source->sourcetype,
            'mod_buchbinder'
        ))),
        'type' => get_string('sourcetype_' . $source->sourcetype, 'mod_buchbinder'),
        'staged' => count($bysource[$sid] ?? []),
        'indocument' => $indocument[$sid] ?? 0,
        'url' => (new moodle_url($baseurl, ['sourceid' => $sid]))->out(false),
        'current' => $sid === $sourceid,
    ];
}

$act = fn($a, $params = []) => (new moodle_url($baseurl, array_merge(['action' => $a, 'sourceid' => $sourceid,
    'sesskey' => sesskey()], $params)))->out(false);
$current = $sourceid ? $sources[$sourceid] : null;
$pages = array_values($bysource[$sourceid] ?? []);
$overlays = $document->get_overlays(array_map(fn($p) => (int)$p->id, $pages));
$flowed = $current && !empty($current->blocks);
$firstright = $document->first_page_right();

// Miniatures, grouped into double pages as they would follow the end of the document.
$base = count($docpages) + 1;
if ($flowed && $current->startright && booklet::side($base, $firstright) !== booklet::RIGHT) {
    $base++;
}
$items = [];
$position = $base;
foreach ($pages as $i => $page) {
    // Automatic blank pages keep chained and pinned pages on their side, as when the pages are taken over.
    $required = $page->spreadid ? $page->spreadside : $page->pinside;
    if ($required && booklet::side($position, $firstright) !== $required) {
        $items[] = ['filler' => true, 'side' => booklet::side($position++, $firstright), 'landscape' => false];
    }
    $items[] = [
        'id' => (int)$page->id,
        'number' => $i + 1,
        'position' => $i + 1,
        'side' => booklet::side($position++, $firstright),
        'landscape' => (bool)$page->landscapelock,
        'thumb' => thumbs::page($document, $page, $overlays[$page->id]),
        'linked' => $page->spreadid ? (string)$page->spreadside : '',
        'pinside' => (string)$page->pinside,
        'pinlabel' => $page->pinside ? get_string('pinned_' . $page->pinside, 'mod_buchbinder') : '',
        'cansplit' => $page->pagetype === 'image' && !$page->spreadid,
        'canlink' => !$page->spreadid && isset($pages[$i + 1]) && !$pages[$i + 1]->spreadid,
        'filler' => false,
    ];
}
// Double pages: a left page followed by a right page share a row; single pages get an empty slot.
$rows = [];
foreach ($items as $item) {
    $last = count($rows) - 1;
    if (
        $last >= 0 && count($rows[$last]['pages']) === 1 && $rows[$last]['pages'][0]['side'] === booklet::LEFT
            && $item['side'] === booklet::RIGHT && !$item['landscape'] && !$rows[$last]['pages'][0]['landscape']
    ) {
        $rows[$last]['pages'][] = $item;
        $rows[$last]['chained'] = ($rows[$last]['pages'][0]['linked'] ?? '') === 'left' && $item['linked'] === 'right';
        continue;
    }
    $rows[] = ['pages' => [$item], 'chained' => false];
}
foreach ($rows as &$row) {
    $single = count($row['pages']) === 1 && !$row['pages'][0]['landscape'];
    $row['ghostleft'] = $single && $row['pages'][0]['side'] === booklet::RIGHT;
    $row['ghostright'] = $single && $row['pages'][0]['side'] === booklet::LEFT;
}
unset($row);

// Passages of continuous sources.
$passages = [];
$flowmap = [];
if ($flowed) {
    $hidden = array_map('intval', json_decode((string)$current->hiddenblocks, true) ?: []);
    foreach ($document->get_source_blocks($sourceid, false) as $block) {
        $label = $block['type'] === 'image' ? get_string('passage_image', 'mod_buchbinder')
            : get_string(
                'passage_' . (preg_match('/^h[1-6]$/', $block['tag']) ? 'heading'
                : (in_array($block['tag'], ['ul', 'ol', 'dl']) ? 'list' : ($block['tag'] === 'table' ? 'table' : 'text'))),
                'mod_buchbinder'
            );
        $passages[] = [
            'index' => $block['index'],
            'type' => $block['type'],
            'tag' => $block['tag'] ?? '',
            'label' => $label,
            'html' => $block['type'] === 'text' ? $block['html'] : '',
            'notes' => $block['notes'] ?? [],
            'preview' => $block['type'] === 'text' ? shorten_text(trim(preg_replace(
                '/\s+/u',
                ' ',
                html_entity_decode(strip_tags(str_replace('<', ' <', $block['html'])), ENT_QUOTES, 'UTF-8')
            )), 160)
                : ($block['alt'] ?: ($block['caption'] ?: ($block['filename'] ?? ''))),
            'hidden' => in_array($block['index'], $hidden, true),
            'adopted' => !empty($block['adopted']),
        ];
    }
    $flowmap = json_decode((string)$current->flowmap, true) ?: [];
}

// Where pages are taken over.
$positions = [['value' => -1, 'label' => get_string('adoptatend', 'mod_buchbinder', count($docpages)), 'selected' => true],
    ['value' => 0, 'label' => get_string('adoptatstart', 'mod_buchbinder')]];
$n = 0;
foreach ($docpages as $page) {
    $n++;
    if ($n > 500) {
        break;
    }
    $positions[] = ['value' => (int)$page->id, 'label' => get_string('adoptafter', 'mod_buchbinder', $n)];
}

$config = [
    'cmid' => $cm->id,
    'sourceid' => $sourceid,
    'flowed' => $flowed,
    'pagestyle' => $flowed ? ($current->pagestyle ?: booklet::STYLE_STANDARD) : '',
    'measurekey' => $flowed ? (string)$current->measurekey : '',
    'pagecount' => count($pages),
    'passages' => $passages,
    'flowmap' => $flowmap,
    'actionurl' => (new moodle_url($baseurl, ['sourceid' => $sourceid, 'sesskey' => sesskey()]))->out(false),
    // Geometry for measuring text like the layout flow sets it.
    'metrics' => [
        'textwidth' => [
            booklet::STYLE_STANDARD => booklet::type_area(booklet::RIGHT)[2],
            booklet::STYLE_TUFTE => booklet::type_area(booklet::RIGHT, booklet::STYLE_TUFTE)[2],
        ],
        'notewidth' => booklet::note_area(booklet::RIGHT)[2],
        'aspect' => booklet::PAGE_HEIGHT_MM / booklet::PAGE_WIDTH_MM,
        'notescale' => flow::NOTE_SCALE,
    ],
];

$jobs = import_queue::export($document, new moodle_url($baseurl, ['action' => 'dismissjob', 'sesskey' => sesskey()]));

$PAGE->set_url($here);
$PAGE->set_title(format_string($instance->name) . ': ' . get_string('importdesk', 'mod_buchbinder'));
$PAGE->set_pagelayout('embedded');
$PAGE->add_body_class('bb-desk-body');
$PAGE->requires->js_call_amd('mod_buchbinder/importdesk', 'init', ['#buchbinder-import']);
if ($jobs['pending']) {
    $PAGE->requires->js_call_amd('mod_buchbinder/jobs', 'init', [$cm->id]);
}

$studio = fn($tab) => (new moodle_url('/mod/buchbinder/studio.php', ['id' => $cm->id, 'tab' => $tab]))->out(false);
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_buchbinder/import_desk', [
    'name' => format_string($instance->name),
    'cmid' => $cm->id,
    'isimport' => true,
    'config' => json_encode($config),
    'forms' => $forms,
    'showimport' => $showimport || !$sources,
    'jobs' => $jobs['hasjobs'] ? $OUTPUT->render_from_template('mod_buchbinder/studio_jobs', $jobs) : '',
    'sources' => $sourcelist,
    'hassources' => !empty($sourcelist),
    'harvesterdisabled' => !harvester::is_enabled(),
    'current' => $current ? [
        'title' => format_string($current->title ?: ($current->filename ?: '')),
        'type' => get_string('sourcetype_' . $current->sourcetype, 'mod_buchbinder'),
        'citation' => document::has_citation($current) ? document::citation($current) : '',
        'pagecount' => count($pages),
        'indocument' => $indocument[$sourceid] ?? 0,
        'flowed' => $flowed,
        'measured' => $flowed && !empty($current->measurekey),
        'tufte' => $flowed && $current->pagestyle === booklet::STYLE_TUFTE,
        'startright' => $flowed && $current->startright,
        'hiddencount' => count(array_filter($passages, fn($p) => $p['hidden'])),
        'isimage' => in_array($current->sourcetype, ['pdf', 'image', 'cbz']),
        'deleteurl' => $act('deletesource'),
        'splitallurl' => $act('splitall'),
        'restyleurl' => $baseurl->out(false),
        'sourceid' => $sourceid,
        'sesskey' => sesskey(),
        'rangeall' => $pages ? '1-' . count($pages) : '',
    ] : null,
    'rows' => $rows,
    'haspages' => !empty($pages),
    'positions' => $positions,
    'adopturl' => $baseurl->out(false),
    'links' => [
        'import' => $baseurl->out(false),
        'desk' => (new moodle_url('/mod/buchbinder/desk.php', ['id' => $cm->id]))->out(false),
        'pages' => $studio('pages'),
        'sources' => $studio('sources'),
        'publish' => $studio('publish'),
        'preview' => (new moodle_url('/mod/buchbinder/view.php', ['id' => $cm->id]))->out(false),
        'print' => (new moodle_url('/mod/buchbinder/print.php', ['id' => $cm->id]))->out(false),
        'layout' => (new moodle_url('/mod/buchbinder/layout.php', ['id' => $cm->id]))->out(false),
        'course' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
        'snippet' => (new moodle_url('/mod/buchbinder/snippet.php', ['id' => $cm->id]))->out(false),
        'assetbank' => has_capability('mod/buchbinder:useassetbank', $context)
            ? (new moodle_url('/mod/buchbinder/assetbank.php', ['id' => $cm->id]))->out(false) : '',
    ],
]);
echo $OUTPUT->footer();
