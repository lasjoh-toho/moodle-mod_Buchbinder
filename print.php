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
 * Eco print studio: download paper and ink saving PDFs.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_buchbinder\local\document;
use mod_buchbinder\local\eco_print;
use mod_buchbinder\local\imposition;

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'buchbinder');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/buchbinder:print', $context);

$document = document::from_cmid($cm->id);
$instance = $document->get_instance();
$caneditor = has_capability('mod/buchbinder:edit', $context);
if (!$instance->enableprint && !$caneditor) {
    throw new moodle_exception('printdisabled', 'mod_buchbinder');
}
$cansolutions = has_capability('mod/buchbinder:viewsolutions', $context);

$url = new moodle_url('/mod/buchbinder/print.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_title(format_string($instance->name) . ': ' . get_string('ecoprint', 'mod_buchbinder'));
$PAGE->set_heading(format_string($course->fullname));

$form = new \mod_buchbinder\form\print_form($url, ['cansolutions' => $cansolutions]);
$form->set_data(['id' => $cm->id]);

if ($data = $form->get_data()) {
    // The print range selects within the published excerpt.
    $published = array_values($document->get_published_pages());
    $numbers = \mod_buchbinder\local\page_range::parse($data->range, count($published));
    $pages = array_map(fn($n) => $published[$n - 1], $numbers);
    $layout = in_array($data->layout, imposition::layouts()) ? $data->layout : imposition::LAYOUT_1UP;
    $printer = new eco_print($document, !empty($data->inksaver), $cansolutions && !empty($data->withsolutions));
    $pdf = $printer->build($pages, $layout);
    $pdf->Output(clean_filename(format_string($instance->name) . '-' . $layout . '.pdf'), 'D');
    exit;
}

echo $OUTPUT->header();
echo html_writer::div(get_string('ecoprint_help', 'mod_buchbinder'), 'mb-3');
$form->display();
echo $OUTPUT->footer();
