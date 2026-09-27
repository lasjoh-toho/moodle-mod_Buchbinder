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
use mod_buchbinder\local\importer;

/**
 * Tests for the import area: staged pages, taking pages over, passages and measured text.
 *
 * @package    mod_buchbinder
 * @category   test
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_buchbinder\local\document
 * @covers     \mod_buchbinder\local\importer
 * @covers     \mod_buchbinder\local\flow
 * @covers     \mod_buchbinder\external\set_source
 */
final class import_area_test extends \advanced_testcase {
    /**
     * New empty document.
     *
     * @return document
     */
    protected function create_document(): document {
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('buchbinder', ['course' => $course->id]);
        return document::from_cmid($instance->cmid);
    }

    public function test_staged_pages_and_range(): void {
        $this->resetAfterTest();
        $document = $this->create_document();
        $document->add_canvas_page();
        $importer = new importer($document, ['stage']);
        $this->assertSame(4, $importer->add_blank_pages('lined', 4, false));
        // Imported pages wait in the import area.
        $this->assertCount(1, $document->get_pages());
        $staged = array_keys($document->get_staged_pages());
        $this->assertCount(4, $staged);

        // Chain pages 2 and 3 of the import, take pages 2-3 over at the end.
        $document->link_spread($staged[1]);
        $this->assertSame(2, $document->adopt_pages([$staged[1], $staged[2]]));
        $pages = array_values($document->get_pages());
        // The double page starts on a left page: page 2.
        $this->assertCount(3, $pages);
        $this->assertEquals($staged[1], $pages[1]->id);
        $this->assertSame('left', $pages[1]->spreadside);
        $this->assertCount(2, $document->get_staged_pages());

        // Take page 1 of the import over at the beginning; the double page keeps its sides.
        $document->adopt_pages([$staged[0]], 0);
        $ids = array_map('intval', array_keys(array_filter($document->get_pages(), fn($p) => !$p->filler)));
        $this->assertSame((int)$staged[0], $ids[0]);
        $this->assertSame([], $document->layout_issues());
    }

    public function test_split_page(): void {
        $this->resetAfterTest();
        $document = $this->create_document();
        $importer = new importer($document, ['stage']);
        $importer->add_blank_pages('blank', 1, true);
        $page = array_values($document->get_staged_pages())[0];
        $this->assertTrue($document->split_page((int)$page->id));
        $halves = array_values($document->get_staged_pages());
        $this->assertCount(2, $halves);
        $this->assertSame(['left', 'right'], [$halves[0]->spreadside, $halves[1]->spreadside]);
        $this->assertLessThan($halves[0]->height, $halves[0]->width);
        $this->assertCount(0, $document->get_pages());
    }

    public function test_passages_and_measure(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $document = $this->create_document();
        $importer = new importer($document, ['stage', 'startright']);
        $sourceid = $document->add_source('md', []);
        $long = str_repeat('Wort ', 400);
        $blocks = blocks::from_markdown("# Titel\n\n{$long}\n\nZweiter Absatz.\n\n{$long}\n");
        $count = $importer->flow($blocks, $sourceid);
        $this->assertGreaterThan(1, $count);
        $this->assertCount(0, $document->get_pages());
        $staged = array_values($document->get_staged_pages($sourceid));
        $this->assertSame(booklet::RIGHT, $staged[0]->layoutside);
        $source = $document->get_source($sourceid);
        $this->assertCount(4, json_decode($source->blocks, true));
        $map = json_decode($source->flowmap, true);
        $this->assertSame([0, 1], array_slice($map[$staged[0]->id], 0, 2));

        // Hide the long paragraphs: one page is left.
        $cm = get_coursemodule_from_instance('buchbinder', $document->get_instance()->id);
        $result = external\set_source::execute($cm->id, $sourceid, json_encode([1, 3]));
        $this->assertSame(1, $result['pages']);

        // Measured text: 100 lines of 0.01 page height do not fit on one page, the paragraph is split at a line.
        $lines = [];
        for ($i = 1; $i <= 100; $i++) {
            $lines[] = $i * 20;
        }
        $measure = [1 => ['h' => 1.01, 'lh' => 0.01, 'lines' => $lines]];
        $result = external\set_source::execute($cm->id, $sourceid, json_encode([3]), json_encode($measure), 'abc');
        $this->assertSame(2, $result['pages']);
        $this->assertSame('abc', $document->get_source($sourceid)->measurekey);
        $staged = array_values($document->get_staged_pages($sourceid));
        $frames = $document->get_overlays([$staged[0]->id])[$staged[0]->id];
        preg_match('#<p>(.*?)</p>#s', end($frames)->settings['html'], $m);
        // The first part ends exactly at the end of a line: n lines of 20 characters without the last space.
        $this->assertSame(19, \core_text::strlen($m[1]) % 20);
        $this->assertGreaterThan(60, \core_text::strlen($m[1]));

        // Taking the first page over marks its passages; they are not set again.
        $document->adopt_pages([$staged[0]->id]);
        $blocksnow = $document->get_source_blocks($sourceid, false);
        $this->assertTrue(!empty($blocksnow[0]['adopted']));
        importer::set_source($document, $sourceid);
        foreach ($document->get_staged_pages($sourceid) as $page) {
            foreach ($document->get_overlays([$page->id])[$page->id] as $frame) {
                $this->assertStringNotContainsString('Titel', $frame->settings['html']);
            }
        }
    }

    public function test_frames_are_mirrored_on_other_side(): void {
        $this->resetAfterTest();
        $document = $this->create_document();
        $importer = new importer($document, ['stage', booklet::STYLE_TUFTE]);
        $sourceid = $document->add_source('md', []);
        $importer->flow(blocks::from_markdown("Text[^1]\n\n[^1]: Note\n", null, true), $sourceid);
        $page = array_values($document->get_staged_pages($sourceid))[0];
        // Page 1 of the empty document is a right page: the note is on the right.
        $note = fn() => array_values(array_filter(
            $document->get_overlays([$page->id])[$page->id],
            fn($o) => $o->settings['style'] === 'sidenote'
        ))[0];
        $this->assertGreaterThan(0.5, (float)$note()->x);
        // Taken over after a first page, it becomes a left page: the note moves to the left margin.
        $document->add_canvas_page();
        $document->adopt_pages([$page->id]);
        $this->assertLessThan(0.2, (float)$note()->x);
        $this->assertSame(booklet::LEFT, $document->get_page($page->id)->layoutside);
    }
}
