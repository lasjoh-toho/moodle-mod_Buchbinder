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

use mod_buchbinder\local\blocks;
use mod_buchbinder\local\booklet;
use mod_buchbinder\local\document;
use mod_buchbinder\local\flow;
use mod_buchbinder\local\image_cleanup;
use mod_buchbinder\local\importer;

/**
 * Tests for the booklet model, the block splitter and the layout flow.
 *
 * @package    mod_buchbinder
 * @category   test
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_buchbinder\local\booklet
 * @covers     \mod_buchbinder\local\blocks
 * @covers     \mod_buchbinder\local\flow
 * @covers     \mod_buchbinder\local\document
 * @covers     \mod_buchbinder\local\importer
 */
final class booklet_flow_test extends \advanced_testcase {
    /**
     * New empty document.
     *
     * @param array $record
     * @return document
     */
    protected function create_document(array $record = []): document {
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('buchbinder', ['course' => $course->id] + $record);
        return document::from_cmid($instance->cmid);
    }

    /**
     * A PNG image.
     *
     * @param int $w
     * @param int $h
     * @return string
     */
    protected function png(int $w, int $h): string {
        return image_cleanup::to_png(imagecreatetruecolor($w, $h));
    }

    /**
     * Upload a file to the draft area.
     *
     * @param string $name
     * @param string $content
     * @return \stored_file
     */
    protected function draft_file(string $name, string $content): \stored_file {
        global $USER;
        return get_file_storage()->create_file_from_string(['contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user', 'filearea' => 'draft', 'itemid' => file_get_unused_draft_itemid(), 'filepath' => '/',
            'filename' => $name], $content);
    }

    public function test_booklet_geometry(): void {
        $this->assertSame(booklet::RIGHT, booklet::side(1));
        $this->assertSame(booklet::LEFT, booklet::side(2));
        $this->assertSame(booklet::LEFT, booklet::side(1, false));
        // The gutter (inner margin) lies at the binding: left on right pages, right on left pages.
        [$rx, , $rw] = booklet::type_area(booklet::RIGHT);
        [$lx, , $lw] = booklet::type_area(booklet::LEFT);
        $this->assertGreaterThan($lx, $rx);
        $this->assertEqualsWithDelta($rw, $lw, 1e-9);
        $this->assertEqualsWithDelta(1 - ($lx + $lw), $rx, 1e-9);

        $pages = array_map(fn($i) => ['position' => $i, 'landscape' => $i === 5], range(1, 7));
        $rows = array_map(fn($r) => array_column($r, 'position'), booklet::spreads($pages));
        $this->assertSame([[1], [2, 3], [4], [5], [6, 7]], $rows);
    }

    public function test_blocks_from_html(): void {
        $this->resetAfterTest();
        $png = $this->png(80, 40);
        $blocks = blocks::from_html(
            'Lose <b>Text</b><h2>Titel</h2><div><p>Absatz <img src="a.png" alt="A"> mit Bild</p></div>' .
            '<ul><li>eins</li></ul><figure><img src="b.png"><figcaption>Karte</figcaption></figure>' .
            '<img src="fehlt.png" alt="Fehlt"><hr class="bb-pagebreak"><table><tr><td>x</td></tr></table>' .
            '<script>alert(1)</script>',
            fn($src) => in_array($src, ['a.png', 'b.png']) ? ['data' => $png, 'filename' => $src] : null
        );
        $summary = array_map(fn($b) => $b['type'] . ($b['type'] === 'text' ? ':' . $b['tag'] : ''), $blocks);
        $this->assertSame(
            ['text:p', 'text:h2', 'text:p', 'image', 'text:ul', 'image', 'text:p', 'pagebreak', 'text:table'],
            $summary
        );
        $this->assertStringContainsString('<b>Text</b>', $blocks[0]['html']);
        $this->assertStringNotContainsString('<img', $blocks[2]['html']);
        $this->assertSame('Karte', $blocks[5]['caption']);
        $this->assertSame([80, 40], [$blocks[5]['width'], $blocks[5]['height']]);
        $this->assertStringContainsString('[Fehlt]', $blocks[6]['html']);
        foreach ($blocks as $block) {
            $this->assertStringNotContainsString('alert', $block['html'] ?? '');
        }
    }

    public function test_blocks_from_markdown(): void {
        $this->resetAfterTest();
        $blocks = blocks::from_markdown("# Titel\n\nText mit *Betonung*.\n\n{{< pagebreak >}}\n\n- a\n- b\n");
        $summary = array_map(fn($b) => $b['type'] . ($b['type'] === 'text' ? ':' . $b['tag'] : ''), $blocks);
        $this->assertSame(['text:h1', 'text:p', 'pagebreak', 'text:ul'], $summary);
        $this->assertStringContainsString('<em>Betonung</em>', $blocks[1]['html']);
    }

    public function test_split_html_text_keeps_markup(): void {
        [$first, $rest] = flow::split_html_text('<p>Ein <strong>langer fetter Text</strong> geht weiter</p>', 22);
        $this->assertSame('<p>Ein <strong>langer fetter</strong></p>', $first);
        $this->assertSame('<p><strong>Text</strong> geht weiter</p>', $rest);
    }

    public function test_flow_fills_pages_in_type_area(): void {
        $this->resetAfterTest();
        $document = $this->create_document();
        $blocks = [['type' => 'text', 'tag' => 'h1', 'html' => '<h1>Kapitel</h1>']];
        for ($i = 0; $i < 40; $i++) {
            $blocks[] = ['type' => 'text', 'tag' => 'p', 'html' => '<p>' . str_repeat('Wort ', 80) . $i . '</p>'];
        }
        $blocks[] = ['type' => 'image', 'data' => $this->png(1200, 600), 'filename' => 'bild.png', 'alt' => 'Bild',
            'caption' => '', 'width' => 1200, 'height' => 600];
        $pages = (new flow($document, null))->run($blocks);
        $this->assertGreaterThan(3, count($pages));

        $positions = $document->get_positions();
        $overlays = $document->get_overlays(array_map(fn($p) => (int)$p->id, $pages));
        $alltext = '';
        $images = 0;
        foreach ($pages as $page) {
            $area = booklet::type_area($document->side_at($positions[$page->id]));
            foreach ($overlays[$page->id] as $frame) {
                $this->assertEqualsWithDelta($area[0], $frame->x, 0.001, 'Frames start at the mirrored margin.');
                $this->assertGreaterThanOrEqual($area[1] - 1e-6, $frame->y);
                $this->assertLessThanOrEqual($area[1] + $area[3] + 1e-6, $frame->y + $frame->h);
                if ($frame->overlaytype === 'textframe') {
                    $alltext .= strip_tags($frame->settings['html']);
                } else {
                    $images++;
                    $this->assertNotNull($document->frame_image_file($frame));
                }
            }
        }
        // Nothing is lost when paragraphs are split between pages.
        $this->assertSame(40 * 80 + 1, substr_count($alltext, 'Wort') + substr_count($alltext, 'Kapitel'));
        $this->assertSame(1, $images);
    }

    public function test_flow_start_right_and_page_breaks(): void {
        $this->resetAfterTest();
        $document = $this->create_document();
        $document->add_canvas_page();
        // The next page would be a left page: a blank page is inserted so that the text starts on the right.
        $blocks = blocks::from_markdown("# A\n\n{{< pagebreak >}}\n\n# B");
        $pages = (new flow($document, null))->run($blocks, true);
        $this->assertCount(2, $pages);
        $positions = $document->get_positions();
        $this->assertSame(3, $positions[$pages[0]->id]);
        $this->assertSame(booklet::RIGHT, $document->side_at($positions[$pages[0]->id]));
        $this->assertCount(4, $document->get_pages());
    }

    public function test_import_markdown_and_docx_as_frames(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $document = $this->create_document();
        $importer = new importer($document, ['startright']);
        $this->assertSame(1, $importer->import_file($this->draft_file('text.md', "# Titel\n\nAbsatz.\n")));
        $page = array_values($document->get_pages())[0];
        $this->assertSame('canvas', $page->pagetype);
        $frames = $document->get_overlays([$page->id])[$page->id];
        $this->assertStringContainsString('<h1>Titel</h1>', $frames[0]->settings['html']);

        // Word document with an embedded image.
        $path = make_request_directory() . '/test.docx';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?>
            <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
                xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body>
            <w:p><w:r><w:t>Vor dem Bild</w:t></w:r></w:p>
            <w:p><w:r><w:drawing><a:graphic><a:graphicData><a:blip r:embed="rId7"/></a:graphicData></a:graphic>
            </w:drawing></w:r></w:p>
            </w:body></w:document>');
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0"?>
            <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
            <Relationship Id="rId7" Type="image" Target="media/image1.png"/></Relationships>');
        $zip->addFromString('word/media/image1.png', $this->png(300, 200));
        $zip->close();
        $importer->import_file($this->draft_file('doc.docx', file_get_contents($path)));
        $pages = array_values($document->get_pages());
        // The Word document starts on a right page: page 2 is a blank padding page.
        $this->assertCount(3, $pages);
        $this->assertEmpty($document->get_overlays([$pages[1]->id])[$pages[1]->id]);
        $types = array_map(fn($f) => $f->overlaytype, $document->get_overlays([$pages[2]->id])[$pages[2]->id]);
        $this->assertSame(['textframe', 'imageframe'], $types);
    }

    public function test_scanned_double_pages_land_on_left_and_right(): void {
        $this->resetAfterTest();
        $document = $this->create_document();
        $importer = new importer($document, ['split']);
        $sourceid = $document->add_source('image', []);
        $scan = imagecreatetruecolor(1600, 1000);
        imagefill($scan, 0, 0, 0xFFFFFF);
        for ($l = 0; $l < 25; $l++) {
            foreach ([[80, 740], [860, 1520]] as [$a, $b]) {
                imagefilledrectangle($scan, $a, 100 + $l * 32, $b, 108 + $l * 32, 0x222222);
            }
        }
        // Page 1 is a right page, so a blank page is inserted and the double page becomes 2|3.
        $this->assertSame(2, $importer->add_scan($scan, $sourceid));
        $pages = array_values($document->get_pages());
        $this->assertCount(3, $pages);
        $this->assertSame('canvas', $pages[0]->pagetype);
        $this->assertSame('left', $pages[1]->spreadside);
        $this->assertSame([], $document->layout_issues());

        // Deleting the blank page breaks the double page; align_spread() repairs it.
        $document->delete_page((int)$pages[0]->id);
        $issues = $document->layout_issues();
        $this->assertCount(1, $issues);
        $this->assertTrue($document->align_spread($issues[0]['leftpageid']));
        $this->assertSame([], $document->layout_issues());
        $this->assertSame('canvas', array_values($document->get_pages())[0]->pagetype);
    }

    public function test_blank_pages_and_moving_frames(): void {
        $this->resetAfterTest();
        $document = $this->create_document();
        $a = $document->add_canvas_page();
        $b = $document->add_canvas_page();
        $first = $document->insert_blank_page((int)$a->id, true);
        $after = $document->insert_blank_page((int)$a->id, false);
        $this->assertSame(
            [(int)$first->id, (int)$a->id, (int)$after->id, (int)$b->id],
            array_map('intval', array_keys($document->get_pages()))
        );

        // A frame dragged across the fold changes its page.
        $frame = $document->save_overlay((int)$a->id, 0, 'textframe', [0.1, 0.1, 0.5, 0.2], ['html' => '<p>x</p>']);
        $moved = $document->save_overlay(
            (int)$b->id,
            (int)$frame->id,
            'textframe',
            [0.2, 0.1, 0.5, 0.2],
            ['html' => '<p>x<script>y()</script></p>']
        );
        $this->assertEquals($b->id, $moved->pageid);
        $this->assertStringNotContainsString('script', $moved->settings['html']);
    }

    public function test_delete_source(): void {
        $this->resetAfterTest();
        $document = $this->create_document();
        $importer = new importer($document);
        $importer->add_snippet('web', '<p>Eins</p>', [], ['title' => 'A']);
        $importer->add_snippet('web', '<p>Zwei</p>', [], ['title' => 'B']);
        [$sa, $sb] = array_keys($document->get_sources());
        $this->assertSame(1, $document->delete_source($sa, true));
        $this->assertCount(1, $document->get_pages());
        $this->assertSame(0, $document->delete_source($sb, false));
        $this->assertCount(1, $document->get_pages());
        $this->assertNull(array_values($document->get_pages())[0]->sourceid);
        $this->assertCount(0, $document->get_sources());
    }
}
