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
 * Publishing studio: import, cleanup, sources, canvas editor and publishing.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_buchbinder\local\document;
use mod_buchbinder\local\harvester;
use mod_buchbinder\local\import_queue;
use mod_buchbinder\local\importer;

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$tab = optional_param('tab', 'import', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHA);
$pageid = optional_param('pageid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'buchbinder');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/buchbinder:edit', $context);

$document = document::from_cmid($cm->id);
$instance = $document->get_instance();
$tabs = ['import', 'pages', 'sources', 'canvas', 'publish'];
if (!in_array($tab, $tabs)) {
    $tab = 'import';
}
$baseurl = new moodle_url('/mod/buchbinder/studio.php', ['id' => $cm->id]);
if ($tab === 'canvas') {
    // The canvas is the full screen layout desk.
    redirect(new moodle_url('/mod/buchbinder/desk.php', ['id' => $cm->id, 'pageid' => $pageid]));
}
$taburl = new moodle_url($baseurl, ['tab' => $tab]);

$PAGE->set_url($taburl);
$PAGE->set_title(format_string($instance->name) . ': ' . get_string('studio', 'mod_buchbinder'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');
$PAGE->activityheader->disable();

// Page actions.
if ($action !== '') {
    require_sesskey();
    $return = new moodle_url($baseurl, ['tab' => 'pages']);
    switch ($action) {
        case 'moveup':
        case 'movedown':
            $document->move_page($pageid, $action === 'moveup' ? -1 : 1);
            break;
        case 'rotate':
            $document->rotate_page($pageid, 90);
            break;
        case 'cleanup':
            $ops = array_intersect(explode(',', required_param('ops', PARAM_TEXT)), ['chop', 'deskew', 'shadow', 'split']);
            $document->cleanup_page($pageid, $ops);
            break;
        case 'cleanupall':
            $ops = array_intersect(optional_param_array('ops', [], PARAM_ALPHA), ['chop', 'deskew', 'shadow', 'split']);
            foreach (array_keys($document->get_pages()) as $pid) {
                $document->cleanup_page($pid, $ops);
            }
            break;
        case 'lock':
        case 'unlock':
            $document->set_landscapelock($pageid, $action === 'lock');
            break;
        case 'linkspread':
            $document->link_spread($pageid);
            break;
        case 'unlinkspread':
            $document->unlink_spread($pageid);
            break;
        case 'dismissjob':
            import_queue::dismiss($document, required_param('jobid', PARAM_INT));
            break;
        case 'delete':
            if (!optional_param('confirm', 0, PARAM_BOOL)) {
                echo $OUTPUT->header();
                echo $OUTPUT->confirm(
                    get_string('confirmdeletepage', 'mod_buchbinder'),
                    new moodle_url($baseurl, ['action' => 'delete', 'pageid' => $pageid, 'confirm' => 1, 'sesskey' => sesskey()]),
                    $return
                );
                echo $OUTPUT->footer();
                exit;
            }
            $document->delete_page($pageid);
            break;
    }
    redirect($return);
}

$maxbytes = document::max_bytes($context);
$content = '';
if (in_array($tab, ['import', 'pages'])) {
    $jobs = import_queue::export($document, new moodle_url($baseurl, ['action' => 'dismissjob', 'sesskey' => sesskey()]));
    if ($jobs['hasjobs']) {
        $content .= $OUTPUT->render_from_template('mod_buchbinder/studio_jobs', $jobs);
        if ($jobs['pending']) {
            $PAGE->requires->js_call_amd('mod_buchbinder/jobs', 'init', [$cm->id]);
        }
    }
}

switch ($tab) {
    case 'import':
        $importform = new \mod_buchbinder\form\import_form(null, ['maxbytes' => $maxbytes]);
        $blankform = new \mod_buchbinder\form\blank_form();
        $webform = null;
        if (harvester::is_enabled() && has_capability('mod/buchbinder:harvest', $context)) {
            $webform = new \mod_buchbinder\form\web_form();
        }
        $form = optional_param('form', '', PARAM_ALPHA);

        if ($form === 'blank' && ($data = $blankform->get_data())) {
            $n = (new importer($document))->add_blank_pages($data->template, (int)$data->count, !empty($data->landscape));
            redirect(new moodle_url($baseurl, ['tab' => 'pages']), get_string('pagesimported', 'mod_buchbinder', $n));
        }
        if ($form === 'web' && $webform && ($data = $webform->get_data())) {
            try {
                $result = harvester::harvest($data->url, $data->selector ?? '', $maxbytes);
                $importer = new importer($document, [$data->pagestyle ?? 'standard']);
                $page = $importer->add_snippet('web', $result['html'], $result['images'], [
                    'title' => $result['title'], 'url' => $result['url'], 'author' => $data->author ?? '',
                ]);
                redirect(
                    new moodle_url('/mod/buchbinder/desk.php', ['id' => $cm->id, 'pageid' => $page->id]),
                    get_string('snippetharvested', 'mod_buchbinder')
                );
            } catch (moodle_exception $e) {
                \core\notification::error($e->getMessage());
            }
        }
        if ($form === '' && ($data = $importform->get_data())) {
            $draftid = file_get_submitted_draft_itemid('files');
            $fs = get_file_storage();
            $usercontext = context_user::instance($USER->id);
            $ops = array_keys(array_filter(['chop' => $data->chop, 'deskew' => $data->deskew, 'shadow' => $data->shadow,
                'split' => $data->split, 'startright' => $data->startright,
                'tufte' => ($data->pagestyle ?? '') === 'tufte']));
            $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftid, 'sortorder, filename', false);
            $job = import_queue::enqueue($document, array_values($files), $ops, ['title' => $data->title,
                'author' => $data->author, 'url' => $data->url ?: null]);
            $fs->delete_area_files($usercontext->id, 'user', 'draft', $draftid);
            if ($job->status === import_queue::STATUS_QUEUED) {
                redirect(new moodle_url($baseurl, ['tab' => 'pages']), get_string('importqueued', 'mod_buchbinder'));
            }
            if ($job->message) {
                \core\notification::error(nl2br(s($job->message)));
            }
            $total = $job->pagecount;
            redirect(new moodle_url($baseurl, ['tab' => 'pages']), get_string('pagesimported', 'mod_buchbinder', $total));
        }

        foreach ([$importform, $blankform, $webform] as $f) {
            if ($f) {
                $f->set_data(['id' => $cm->id]);
                $content .= $f->render();
            }
        }
        $content .= html_writer::div(
            html_writer::link(
                new moodle_url('/mod/buchbinder/layout.php', ['id' => $cm->id]),
                get_string('newlayoutpage', 'mod_buchbinder'),
                ['class' => 'btn btn-primary mr-2 me-2']
            ) .
            html_writer::link(
                new moodle_url('/mod/buchbinder/snippet.php', ['id' => $cm->id]),
                get_string('clipboardsnippet', 'mod_buchbinder'),
                ['class' => 'btn btn-secondary mr-2 me-2']
            ) .
            (has_capability('mod/buchbinder:useassetbank', $context) ?
                html_writer::link(
                    new moodle_url('/mod/buchbinder/assetbank.php', ['id' => $cm->id]),
                    get_string('assetbank', 'mod_buchbinder'),
                    ['class' => 'btn btn-secondary']
                ) : ''),
            'my-3'
        );
        if (!harvester::is_enabled()) {
            $content .= html_writer::div(get_string('harvesterdisabled', 'mod_buchbinder'), 'text-muted small');
        }
        break;

    case 'pages':
        $pages = array_values($document->get_pages());
        $sources = $document->get_sources();
        $items = [];
        foreach ($pages as $i => $page) {
            $act = fn($a, $extra = []) => (new moodle_url(
                $baseurl,
                array_merge(['action' => $a, 'pageid' => $page->id, 'sesskey' => sesskey()], $extra)
            ))->out(false);
            $isimage = $page->pagetype === 'image';
            $items[] = [
                'id' => $page->id,
                'number' => $i + 1,
                'isimage' => $isimage,
                'thumb' => $isimage ? $document->page_image_url($page)->out(false) : null,
                'excerpt' => $isimage ? '' : shorten_text(html_to_text($page->content ?? '', 0, false), 120),
                'source' => $page->sourceid && isset($sources[$page->sourceid])
                    ? format_string($sources[$page->sourceid]->title ?: $sources[$page->sourceid]->filename) : '',
                'spreadside' => $page->spreadside ? get_string('spread_' . $page->spreadside, 'mod_buchbinder') : '',
                'isspread' => !empty($page->spreadid),
                'canlink' => empty($page->spreadid) && isset($pages[$i + 1]) && empty($pages[$i + 1]->spreadid),
                'landscapelock' => (bool)$page->landscapelock,
                'size' => $page->width . ' × ' . $page->height,
                'urls' => [
                    'moveup' => $act('moveup'),
                    'movedown' => $act('movedown'),
                    'rotate' => $act('rotate'),
                    'chop' => $act('cleanup', ['ops' => 'chop']),
                    'deskew' => $act('cleanup', ['ops' => 'deskew']),
                    'shadow' => $act('cleanup', ['ops' => 'shadow']),
                    'split' => $act('cleanup', ['ops' => 'split']),
                    'lock' => $act($page->landscapelock ? 'unlock' : 'lock'),
                    'link' => $act('linkspread'),
                    'unlink' => $act('unlinkspread'),
                    'delete' => $act('delete'),
                    'canvas' => (new moodle_url('/mod/buchbinder/desk.php', ['id' => $cm->id, 'pageid' => $page->id]))
                        ->out(false),
                    'edit' => (new moodle_url($page->pagetype === 'layout' ? '/mod/buchbinder/layout.php' :
                        '/mod/buchbinder/snippet.php', ['id' => $cm->id, 'pageid' => $page->id]))->out(false),
                ],
            ];
        }
        $content .= html_writer::div(html_writer::link(
            new moodle_url('/mod/buchbinder/layout.php', ['id' => $cm->id]),
            get_string('newlayoutpage', 'mod_buchbinder'),
            ['class' => 'btn btn-primary']
        ), 'mb-3');
        $content .= $OUTPUT->render_from_template('mod_buchbinder/studio_pages', [
            'pages' => $items,
            'haspages' => !empty($items),
            'cleanupallurl' => $baseurl->out(false),
            'cmid' => $cm->id,
            'sesskey' => sesskey(),
        ]);
        break;

    case 'sources':
        $rows = [];
        foreach ($document->get_sources() as $source) {
            $rows[] = [
                'type' => get_string('sourcetype_' . $source->sourcetype, 'mod_buchbinder'),
                'title' => format_string($source->title ?: $source->filename),
                'citation' => document::citation($source),
                'showcitation' => document::has_citation($source),
                'editurl' => (new moodle_url('/mod/buchbinder/source.php', ['cmid' => $cm->id, 'id' => $source->id]))->out(false),
                'deleteurl' => (new moodle_url('/mod/buchbinder/source.php', ['cmid' => $cm->id, 'id' => $source->id,
                    'delete' => 'ask']))->out(false),
                'pages' => $DB->count_records('buchbinder_page', ['buchbinderid' => $instance->id, 'sourceid' => $source->id]),
            ];
        }
        $content .= $OUTPUT->render_from_template('mod_buchbinder/studio_sources', ['sources' => $rows,
            'hassources' => !empty($rows)]);
        break;

    case 'publish':
        $publishform = new \mod_buchbinder\form\publish_form();
        if ($data = $publishform->get_data()) {
            $DB->update_record('buchbinder', (object)['id' => $instance->id, 'pagerange' => trim($data->pagerange),
                'enablereflow' => $data->enablereflow, 'enableprint' => $data->enableprint,
                'firstpageright' => $data->firstpageright,
                'ismaster' => $data->ismaster,
                'timemodified' => time()]);
            redirect($taburl, get_string('changessaved'));
        }
        $publishform->set_data(['id' => $cm->id] + (array)$instance);
        $content .= $publishform->render();

        // Asset bank tracking: where is this document used, where does it come from?
        $usage = [];
        foreach ($DB->get_records('buchbinder', ['masterid' => $instance->id]) as $derived) {
            $dcm = get_coursemodule_from_instance('buchbinder', $derived->id);
            $dcourse = get_course($derived->course);
            $usage[] = [
                'name' => format_string($derived->name),
                'course' => format_string($dcourse->fullname),
                'range' => $derived->masterrange ?: get_string('allpages', 'mod_buchbinder'),
                'url' => (new moodle_url('/mod/buchbinder/view.php', ['id' => $dcm->id]))->out(false),
            ];
        }
        $master = null;
        if ($instance->masterid && ($m = $DB->get_record('buchbinder', ['id' => $instance->masterid]))) {
            $mcm = get_coursemodule_from_instance('buchbinder', $m->id);
            $master = [
                'name' => format_string($m->name),
                'range' => $instance->masterrange ?: get_string('allpages', 'mod_buchbinder'),
                'url' => (new moodle_url('/mod/buchbinder/view.php', ['id' => $mcm->id]))->out(false),
            ];
        }
        $content .= $OUTPUT->render_from_template('mod_buchbinder/studio_publish', [
            'usage' => $usage,
            'hasusage' => !empty($usage),
            'master' => $master,
            'viewurl' => (new moodle_url('/mod/buchbinder/view.php', ['id' => $cm->id]))->out(false),
            'printurl' => (new moodle_url('/mod/buchbinder/print.php', ['id' => $cm->id]))->out(false),
        ]);
        break;
}

$tabrow = [];
foreach ($tabs as $i => $t) {
    $tabrow[] = new tabobject(
        $t,
        $t === 'canvas' ? new moodle_url('/mod/buchbinder/desk.php', ['id' => $cm->id]) : new moodle_url($baseurl, ['tab' => $t]),
        ($i + 1) . '. ' . get_string('tab_' . $t, 'mod_buchbinder')
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($instance->name) . ' – ' . get_string('studio', 'mod_buchbinder'));
echo $OUTPUT->tabtree($tabrow, $tab);
echo $content;
echo $OUTPUT->footer();
