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

use mod_buchbinder\local\booklet;
use mod_buchbinder\local\document;
use mod_buchbinder\local\importer;

/**
 * Tests for the document model.
 *
 * @package    mod_buchbinder
 * @category   test
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_buchbinder\local\document
 */
final class document_test extends \advanced_testcase {
    /**
     * Create an activity with blank pages.
     *
     * @param int $pages
     * @param array $record
     * @return document
     */
    protected function create_document(int $pages, array $record = []): document {
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('buchbinder', ['course' => $course->id] + $record);
        $document = document::from_cmid($instance->cmid);
        (new importer($document))->add_blank_pages('lined', $pages, false);
        return $document;
    }

    public function test_published_excerpt(): void {
        $this->resetAfterTest();
        $document = $this->create_document(6, ['pagerange' => '2-3, 6']);
        $this->assertSame([2, 3, 6], array_values(array_map(fn($p) => $p->pagenumber, $document->get_published_pages())));
    }

    public function test_spreads_move_together(): void {
        $this->resetAfterTest();
        $document = $this->create_document(4);
        [$a, $b, $c, $d] = array_keys($document->get_pages());
        $document->link_spread($b);
        $ordinary = fn() => array_keys(array_filter($document->get_pages(), fn($p) => !$p->filler));
        $document->move_page($c, -1);
        // Page c belongs to the double page b|c, so the whole double page moves before a. It must start on a
        // left page, so an automatic blank page becomes page 1.
        $this->assertSame([$b, $c, $a, $d], $ordinary());
        $this->assertCount(5, $document->get_pages());
        $this->assertEquals(1, array_values($document->get_pages())[0]->filler);
        $document->move_page($d, -1);
        $this->assertSame([$b, $c, $d, $a], $ordinary());
    }

    public function test_pinned_pages_keep_their_side(): void {
        $this->resetAfterTest();
        $document = $this->create_document(3);
        [$a, $b, $c] = array_keys($document->get_pages());
        // Page c (position 3) is a right page; pin it there.
        $document->set_pinside($c, booklet::RIGHT);
        $this->assertCount(3, $document->get_pages());
        // Deleting page a would move c to a left page: an automatic blank page keeps it on the right.
        $document->delete_page($a);
        $pages = array_values($document->get_pages());
        $this->assertCount(3, $pages);
        $this->assertEquals(1, $pages[1]->filler);
        $this->assertSame(3, $document->get_positions()[$c]);
        // Inserting a page before b makes the blank page superfluous: it disappears.
        $document->insert_blank_page($b, true);
        $this->assertCount(3, $document->get_pages());
        $this->assertSame(3, $document->get_positions()[$c]);
        $this->assertSame([], array_filter($document->get_pages(), fn($p) => $p->filler));
        // Content on an automatic blank page makes it an ordinary page.
        $document->set_pinside($c, booklet::LEFT);
        $filler = array_values(array_filter($document->get_pages(), fn($p) => $p->filler))[0];
        $document->save_overlay((int)$filler->id, 0, 'textframe', [0.1, 0.1, 0.5, 0.2], ['html' => '<p>x</p>']);
        $document->set_pinside($c, null);
        $this->assertCount(4, $document->get_pages());
        $this->assertSame([], $document->layout_issues());
    }

    public function test_overlays(): void {
        $this->resetAfterTest();
        $document = $this->create_document(1);
        $pageid = array_key_first($document->get_pages());
        $overlay = $document->save_overlay(
            $pageid,
            0,
            'textbox',
            [0.1, 0.2, 2, 0.1],
            ['text' => '<b>Hi</b>', 'color' => 'red', 'fontsize' => 500]
        );
        $this->assertEqualsWithDelta(0.9, $overlay->w, 0.0001);
        $this->assertSame('Hi', $overlay->settings['text']);
        $this->assertSame('#000000', $overlay->settings['color']);
        $this->assertSame(96, $overlay->settings['fontsize']);

        $mask = $document->save_overlay($pageid, 0, 'mask', [0, 0, 0.5, 0.5], ['style' => 'black']);
        $this->assertCount(2, $document->get_overlays([$pageid])[$pageid]);
        $document->delete_overlay($mask->id);
        $this->assertCount(1, $document->get_overlays([$pageid])[$pageid]);
    }

    public function test_asset_bank_copy(): void {
        $this->resetAfterTest();
        $master = $this->create_document(5, ['ismaster' => 1]);
        $pages = array_keys($master->get_pages());
        $master->save_overlay($pages[2], 0, 'mask', [0, 0, 0.5, 0.5], []);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('buchbinder', ['course' => $course->id]);
        $target = document::from_cmid($instance->cmid);
        $this->assertSame(2, $target->copy_pages_from($master, '3-4'));

        $copied = array_keys($target->get_pages());
        $this->assertCount(2, $copied);
        $this->assertCount(1, $target->get_overlays($copied)[$copied[0]]);
        $this->assertNotNull($target->get_page_file($target->get_page($copied[0])));

        global $DB;
        $this->assertEquals($master->get_instance()->id, $DB->get_field('buchbinder', 'masterid', ['id' => $instance->id]));
    }

    public function test_learner_image_burns_in_fixed_masks(): void {
        $this->resetAfterTest();
        $document = $this->create_document(1);
        $page = $document->get_page(array_key_first($document->get_pages()));
        $this->assertEquals($document->get_page_file($page)->get_id(), $document->get_learner_page_file($page)->get_id());

        // Revealable masks stay client side, fixed masks are burned in.
        $document->save_overlay($page->id, 0, 'mask', [0, 0, 0.5, 0.5], ['style' => 'black', 'revealable' => 1]);
        $this->assertSame('page', $document->get_learner_page_file($page)->get_filearea());
        $document->save_overlay($page->id, 0, 'mask', [0, 0, 0.5, 0.5], ['style' => 'black']);
        $file = $document->get_learner_page_file($page);
        $this->assertSame('pagemasked', $file->get_filearea());
        $img = imagecreatefromstring($file->get_content());
        $this->assertSame(0, imagecolorat($img, 10, 10) & 0xFFFFFF);
        // Cached.
        $this->assertEquals($file->get_id(), $document->get_learner_page_file($page)->get_id());
    }
}
