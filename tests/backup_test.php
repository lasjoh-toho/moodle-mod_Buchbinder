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

use mod_buchbinder\local\document;
use mod_buchbinder\local\importer;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Backup and restore tests.
 *
 * @package    mod_buchbinder
 * @category   test
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_buchbinder_activity_structure_step
 * @covers     \restore_buchbinder_activity_structure_step
 * @covers     \restore_buchbinder_activity_task
 */
final class backup_test extends \advanced_testcase {
    /**
     * Course with a glossary and a document with spread, overlays, audio and a snippet.
     *
     * @return array [course, glossary, cm]
     */
    protected function create_fixture(): array {
        $course = $this->getDataGenerator()->create_course();
        $glossary = $this->getDataGenerator()->create_module('glossary', ['course' => $course->id]);
        $instance = $this->getDataGenerator()->create_module('buchbinder', ['course' => $course->id, 'pagerange' => '1-3',
            'firstpageright' => 0]);
        $document = document::from_cmid($instance->cmid);
        $importer = new importer($document);
        $importer->add_blank_pages('lined', 2, false);
        $img = imagecreatetruecolor(40, 30);
        $importer->add_snippet(
            'web',
            '<p>Text</p><p><img src="@@PLUGINFILE@@/a.png" alt="Bild"></p>',
            ['a.png' => local\image_cleanup::to_png($img)],
            ['title' => 'Quelle', 'url' => 'https://example.org']
        );
        [$p1, $p2] = array_keys($document->get_pages());
        $document->link_spread($p1);
        $document->save_overlay($p1, 0, 'glossary', [0, 0, 0.1, 0.1], ['glossaryid' => $glossary->id, 'term' => 'x']);
        $audio = $document->save_overlay($p2, 0, 'audio', [0, 0, 0.1, 0.1], []);
        $document->save_audio($audio->id, 'a.mp3', 'ID3');
        return [$course, $glossary, get_coursemodule_from_id('buchbinder', $instance->cmid)];
    }

    /**
     * Compare a restored document with the fixture.
     *
     * @param document $document
     * @param int $glossaryid expected glossary of the glossary overlay
     */
    protected function assert_document(document $document, int $glossaryid): void {
        $pages = array_values($document->get_pages());
        $this->assertCount(3, $pages);
        $this->assertSame('1-3', $document->get_instance()->pagerange);
        // The double page points to the restored left page.
        $this->assertEquals($pages[0]->id, $pages[0]->spreadid);
        $this->assertEquals($pages[0]->id, $pages[1]->spreadid);
        $this->assertSame('right', $pages[1]->spreadside);
        $this->assertNotNull($document->get_page_file($pages[0]));
        // The snippet page keeps its source, its text frame and its image frame with the image.
        $this->assertSame('canvas', $pages[2]->pagetype);
        $sources = $document->get_sources();
        $this->assertSame('Quelle', $sources[$pages[2]->sourceid]->title);
        $frames = $document->get_overlays([$pages[2]->id])[$pages[2]->id];
        $types = array_map(fn($f) => $f->overlaytype, $frames);
        $this->assertSame(['textframe', 'imageframe'], $types);
        $this->assertStringContainsString('Text', $frames[0]->settings['html']);
        $this->assertNotNull($document->frame_image_file($frames[1]));
        $fs = get_file_storage();

        $overlays = $document->get_overlays([$pages[0]->id, $pages[1]->id]);
        $this->assertEquals($glossaryid, $overlays[$pages[0]->id][0]->settings['glossaryid']);
        $audio = $overlays[$pages[1]->id][0];
        $this->assertSame('ID3', $fs->get_file(
            $document->get_context()->id,
            'mod_buchbinder',
            'audio',
            $audio->id,
            '/',
            'a.mp3'
        )->get_content());
    }

    public function test_duplicate_activity(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $glossary, $cm] = $this->create_fixture();
        $newcm = duplicate_module($course, $cm);
        $this->assert_document(document::from_cmid($newcm->id), $glossary->id);
    }

    public function test_course_backup_restore(): void {
        global $USER, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course] = $this->create_fixture();

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        $newcourseid = \restore_dbops::create_new_course('Restored', 'restored', $course->category);
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $newglossary = $DB->get_record('glossary', ['course' => $newcourseid], '*', MUST_EXIST);
        $newinstance = $DB->get_record('buchbinder', ['course' => $newcourseid], '*', MUST_EXIST);
        $newcm = get_coursemodule_from_instance('buchbinder', $newinstance->id);
        // The glossary overlay now points to the glossary of the new course.
        $this->assert_document(document::from_cmid($newcm->id), $newglossary->id);
    }
}
