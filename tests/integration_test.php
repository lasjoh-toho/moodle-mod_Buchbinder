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

namespace mod_buchbinder;

use core_external\external_api;
use mod_buchbinder\local\document;
use mod_buchbinder\local\docx_reader;
use mod_buchbinder\local\eco_print;
use mod_buchbinder\local\harvester;
use mod_buchbinder\local\importer;

/**
 * Integration tests: web services, print engine, Word import, harvester helpers.
 *
 * @package    mod_buchbinder
 * @category   test
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_buchbinder\external\save_overlay
 * @covers     \mod_buchbinder\external\get_glossary_entry
 * @covers     \mod_buchbinder\local\eco_print
 * @covers     \mod_buchbinder\local\docx_reader
 * @covers     \mod_buchbinder\local\harvester
 */
final class integration_test extends \advanced_testcase {
    public function test_save_overlay_requires_edit_capability(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('buchbinder', ['course' => $course->id]);
        $document = document::from_cmid($instance->cmid);
        (new importer($document))->add_blank_pages('blank', 1, false);
        $pageid = array_key_first($document->get_pages());

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $result = external\save_overlay::execute(
            $instance->cmid,
            $pageid,
            0,
            'mask',
            0.1,
            0.1,
            0.2,
            0.2,
            json_encode(['style' => 'black', 'revealable' => true])
        );
        $result = external_api::clean_returnvalue(external\save_overlay::execute_returns(), $result);
        $this->assertSame('black', json_decode($result['settings'], true)['style']);

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        external\save_overlay::execute($instance->cmid, $pageid, 0, 'mask', 0, 0, 0.1, 0.1, '{}');
    }

    public function test_glossary_entry(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('buchbinder', ['course' => $course->id]);
        $glossary = $this->getDataGenerator()->create_module('glossary', ['course' => $course->id]);
        $this->getDataGenerator()->get_plugin_generator('mod_glossary')->create_content(
            $glossary,
            ['concept' => 'Photosynthese', 'definition' => 'Licht wird zu Energie.'],
            ['Fotosynthese']
        );
        $document = document::from_cmid($instance->cmid);
        (new importer($document))->add_blank_pages('blank', 1, false);
        $pageid = array_key_first($document->get_pages());
        $overlay = $document->save_overlay(
            $pageid,
            0,
            'glossary',
            [0, 0, 0.1, 0.1],
            ['glossaryid' => $glossary->id, 'term' => 'fotosynthese']
        );

        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));
        $result = external\get_glossary_entry::execute($instance->cmid, $overlay->id);
        $result = external_api::clean_returnvalue(external\get_glossary_entry::execute_returns(), $result);
        $this->assertTrue($result['found']);
        $this->assertSame('Photosynthese', $result['concept']);
        $this->assertStringContainsString('Energie', $result['definition']);
    }

    public function test_eco_print(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('buchbinder', ['course' => $course->id]);
        $document = document::from_cmid($instance->cmid);
        $importer = new importer($document);
        $importer->add_blank_pages('squared', 4, false);
        $importer->add_snippet('web', '<h2>Titel</h2><p>Text</p>', [], ['title' => 'Beispiel', 'url' => 'https://example.org']);
        $pageid = array_key_first($document->get_pages());
        $document->save_overlay($pageid, 0, 'mask', [0.1, 0.1, 0.3, 0.1], ['style' => 'black']);
        $document->save_overlay($pageid, 0, 'textbox', [0.1, 0.3, 0.5, 0.1], ['text' => 'Überschrieben', 'fontsize' => 20]);

        foreach (local\imposition::layouts() as $layout) {
            $pdf = (new eco_print($document, true, false))->build($document->get_published_pages(), $layout);
            $this->assertSame(count(local\imposition::sides($layout, 5)), $pdf->getNumPages(), $layout);
            $this->assertStringStartsWith('%PDF', $pdf->Output('', 'S'));
        }
    }

    public function test_docx_reader(): void {
        $path = make_request_directory() . '/test.docx';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?>
            <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>
            <w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Kapitel</w:t></w:r></w:p>
            <w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Fett</w:t></w:r><w:r><w:t> &amp; normal</w:t></w:r></w:p>
            <w:p><w:r><w:br w:type="page"/></w:r></w:p>
            <w:p><w:pPr><w:numPr/></w:pPr><w:r><w:t>Punkt</w:t></w:r></w:p>
            <w:tbl><w:tr><w:tc><w:p><w:r><w:t>A1</w:t></w:r></w:p></w:tc></w:tr></w:tbl>
            </w:body></w:document>');
        $zip->close();
        $pages = docx_reader::to_html_pages($path);
        $this->assertCount(2, $pages);
        $this->assertStringContainsString('<h2>Kapitel</h2>', $pages[0]);
        $this->assertStringContainsString('<strong>Fett</strong> &amp; normal', $pages[0]);
        $this->assertStringContainsString('<ul><li>Punkt</li></ul>', $pages[1]);
        $this->assertStringContainsString('<td>A1</td>', $pages[1]);
    }

    public function test_harvester_helpers(): void {
        $base = 'https://example.org/wiki/a/b.html';
        $this->assertSame('https://example.org/img/x.png', harvester::absolute_url($base, '/img/x.png'));
        $this->assertSame('https://example.org/wiki/c.png', harvester::absolute_url($base, '../c.png'));
        $this->assertSame('https://example.org/wiki/a/d.png', harvester::absolute_url($base, './d.png'));
        $this->assertSame('https://cdn.org/e.png', harvester::absolute_url($base, '//cdn.org/e.png'));
        $this->assertNull(harvester::absolute_url($base, 'javascript:alert(1)'));
        $this->assertSame("//*[@id='content']", harvester::selector_to_xpath('#content'));
        $this->assertSame(
            "//table[contains(concat(' ', normalize-space(@class), ' '), ' data ')]",
            harvester::selector_to_xpath('table.data')
        );
        $this->assertNull(harvester::selector_to_xpath("a'] | //script"));
    }

    public function test_harvester_disabled(): void {
        $this->resetAfterTest();
        set_config('enableharvester', 0, 'buchbinder');
        $this->expectException(\moodle_exception::class);
        harvester::harvest('https://example.org', '', 0);
    }

    public function test_templates_render(): void {
        global $PAGE;
        $this->resetAfterTest();
        $PAGE->set_url('/');
        $output = $PAGE->get_renderer('core');
        foreach (['view', 'editor', 'studio_pages', 'studio_sources', 'studio_publish', 'assetbank'] as $template) {
            $html = $output->render_from_template('mod_buchbinder/' . $template, ['cmid' => 1, 'config' => '{}']);
            $this->assertIsString($html);
        }
    }
}
