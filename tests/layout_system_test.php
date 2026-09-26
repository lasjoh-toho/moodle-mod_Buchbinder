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
use mod_buchbinder\local\import_queue;
use mod_buchbinder\local\importer;
use mod_buchbinder\local\layout_renderer;

/**
 * Tests for the layout system, clips and background imports.
 *
 * @package    mod_buchbinder
 * @category   test
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_buchbinder\local\layout_renderer
 * @covers     \mod_buchbinder\local\import_queue
 * @covers     \mod_buchbinder\task\import_job
 */
final class layout_system_test extends \advanced_testcase {
    /**
     * Renderer that knows one clip.
     *
     * @param bool $print
     * @return layout_renderer
     */
    protected function renderer(bool $print = false): layout_renderer {
        return new layout_renderer(fn($src) => $src === 'ausschnitt-1.png' ? '/clip/1.png' : null, $print);
    }

    public function test_split_pages(): void {
        $pages = layout_renderer::split_pages("A\n\n{{< pagebreak >}}\n\nB\n~~~\n{{< pagebreak >}}\n~~~\n\\newpage\nC");
        $this->assertCount(3, $pages);
        $this->assertSame('A', $pages[0]);
        $this->assertStringContainsString('{{< pagebreak >}}', $pages[1], 'Page breaks in code blocks are kept.');
        $this->assertSame('C', $pages[2]);
        $this->assertSame([''], layout_renderer::split_pages(''));
    }

    public function test_attributes(): void {
        $a = layout_renderer::parse_attributes('.column .x #main width="40%" n=6 title=\'Hallo Welt\'');
        $this->assertSame(['column', 'x'], $a['classes']);
        $this->assertSame('main', $a['id']);
        $this->assertSame(['width' => '40%', 'n' => '6', 'title' => 'Hallo Welt'], $a['kv']);
    }

    public function test_columns_callouts_lines(): void {
        $this->resetAfterTest();
        $html = $this->renderer()->render(
            "# Titel\n\n::: {.columns}\n::: {.column width=\"38%\"}\n![Karte](ausschnitt-1.png)\n:::\n" .
            "::: {.column width=\"62%\"}\n**fett**\n\n::: {.callout-warning}\n## Achtung\nText\n:::\n:::\n:::\n\n" .
            "::: {.lines n=3}\nAntwort:\n:::\n\n::: {.callout-tip}\nOhne Titel\n:::\n"
        );
        $this->assertStringContainsString('<h1>Titel</h1>', $html);
        $this->assertStringContainsString('<div class="bb-l-column bb-l-w-40">', $html);
        $this->assertStringContainsString('<div class="bb-l-column bb-l-w-60">', $html);
        $this->assertMatchesRegularExpression('#<figure class="bb-l-figure"><img src="/clip/1.png" alt="Karte"#', $html);
        $this->assertStringContainsString('<figcaption>Karte</figcaption>', $html);
        $this->assertStringContainsString('<div class="bb-l-callout bb-l-callout-warning"><div class="bb-l-callout-title">' .
            'Achtung</div>', $html);
        $this->assertStringContainsString('<strong>fett</strong>', $html);
        $this->assertSame(3, substr_count($html, 'class="bb-l-line"'));
        $this->assertStringContainsString(
            '<div class="bb-l-callout-title">' . get_string('callout_tip', 'mod_buchbinder'),
            $html
        );
    }

    public function test_images(): void {
        $html = $this->renderer()->render("Text ![](ausschnitt-1.png){width=50%} und ![x](unbekannt.png) " .
            "![w](https://example.org/a.png)");
        $this->assertStringContainsString('<img src="/clip/1.png" alt="" class="bb-l-img" style="width:50%"', $html);
        $this->assertStringContainsString('<em>[x]</em>', $html);
        $this->assertStringContainsString('src="https://example.org/a.png"', $html);
        // Print: no external images, widths as attributes.
        $print = $this->renderer(true)->render("![](ausschnitt-1.png){width=50%} ![w](https://example.org/a.png)");
        $this->assertStringContainsString('width="50%"', $print);
        $this->assertStringNotContainsString('example.org', $print);
    }

    public function test_print_mode_uses_tables(): void {
        $html = $this->renderer(true)->render("::: {.columns}\n::: {.column width=\"50%\"}\nA\n:::\n" .
            "::: {.column width=\"50%\"}\nB\n:::\n:::\n\n::: {.lines n=2}\n:::\n");
        $this->assertStringContainsString('<td width="50%" valign="top">', $html);
        $this->assertSame(2, substr_count($html, 'border-bottom'));
    }

    public function test_unsafe_content_is_removed(): void {
        $html = $this->renderer()->render("<script>alert(1)</script>\n\n<a href=\"javascript:x()\" onclick=\"y()\">a</a>\n\n" .
            "::: {.x\"onmouseover=alert(1) .ok}\nText\n:::\n");
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('onmouseover', $html);
    }

    public function test_layout_pages_and_clips(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('buchbinder', ['course' => $course->id, 'ismaster' => 1]);
        $document = document::from_cmid($instance->cmid);
        (new importer($document))->add_blank_pages('squared', 1, false);
        $imagepage = array_key_first($document->get_pages());

        $this->assertSame('ausschnitt-1.png', $document->create_clip($imagepage, [0.1, 0.1, 0.5, 0.25]));
        $this->assertSame('ausschnitt-2.png', $document->create_clip($imagepage, [0, 0, 1, 1]));
        $clip = imagecreatefromstring($document->get_clips()['ausschnitt-1.png']->get_content());
        $this->assertEqualsWithDelta(0.5 * $document->get_page($imagepage)->width, imagesx($clip), 1);

        $pages = $document->add_layout_pages("# Eins\n![Bild](ausschnitt-1.png)\n{{< pagebreak >}}\n# Zwei");
        $this->assertCount(2, $pages);
        $html = $document->page_html($document->get_page($pages[0]->id));
        $this->assertStringContainsString('/mod_buchbinder/clips/0/ausschnitt-1.png', $html);

        // Updating with a page break inserts the new page right after the edited one.
        $this->assertSame(2, $document->update_layout_page($pages[0]->id, "# Eins\n{{< pagebreak >}}\n# Eineinhalb"));
        $contents = array_values(array_map(fn($p) => $p->content, $document->get_pages()));
        $this->assertSame(['# Eins', '# Eineinhalb', '# Zwei'], array_slice($contents, 1));

        // Printing renders composed pages through the layout renderer.
        $printer = new class ($document, false, false) extends local\eco_print {
            /** @var array page ids rendered as html */
            public $rendered = [];
            /**
             * Record the rendered page type.
             *
             * @param \stdClass $page
             * @param float $x
             * @param float $y
             * @param float $w
             * @param float $h
             */
            protected function render_html(\stdClass $page, float $x, float $y, float $w, float $h): void {
                $this->rendered[] = $page->pagetype;
                parent::render_html($page, $x, $y, $w, $h);
            }
        };
        $pdf = $printer->build($document->get_published_pages(), '2up');
        $this->assertSame(['layout', 'layout', 'layout'], $printer->rendered);
        $this->assertStringStartsWith('%PDF', $pdf->Output('', 'S'));

        // The asset bank copies the clips along with the pages.
        $target = $this->getDataGenerator()->create_module('buchbinder', ['course' => $course->id]);
        $targetdoc = document::from_cmid($target->cmid);
        $targetdoc->copy_pages_from($document, '2');
        $this->assertArrayHasKey('ausschnitt-1.png', $targetdoc->get_clips());
    }

    /**
     * Upload a file to the draft area of the current user.
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

    public function test_background_import(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('buchbinder', ['course' => $course->id]);
        $document = document::from_cmid($instance->cmid);

        $img = imagecreatetruecolor(300, 400);
        imagefill($img, 0, 0, 0xFFFFFF);
        $png = local\image_cleanup::to_png($img);
        $job = import_queue::enqueue(
            $document,
            [$this->draft_file('a.png', $png), $this->draft_file('b.txt', 'x')],
            ['chop'],
            ['title' => 'Scan']
        );
        $this->assertSame(import_queue::STATUS_QUEUED, $job->status);
        $this->assertCount(0, $document->get_pages());
        $this->assertCount(1, import_queue::export($document, new \moodle_url('/'))['jobs']);

        ob_start();
        $this->runAdhocTasks(task\import_job::class);
        ob_end_clean();

        $job = $DB->get_record('buchbinder_job', ['id' => $job->id]);
        $this->assertSame(import_queue::STATUS_DONE, $job->status);
        $this->assertEquals(1, $job->pagecount);
        $this->assertStringContainsString('b.txt', $job->message);
        $this->assertCount(1, $document->get_pages());
        $this->assertFalse(get_file_storage()->file_exists(
            $document->get_context()->id,
            'mod_buchbinder',
            'jobfile',
            $job->id,
            '/',
            'a.png'
        ));

        import_queue::dismiss($document, $job->id);
        $this->assertFalse($DB->record_exists('buchbinder_job', ['id' => $job->id]));

        // Without background processing the import runs immediately.
        set_config('backgroundimport', 0, 'buchbinder');
        $job = import_queue::enqueue($document, [$this->draft_file('c.txt', 'x')], [], []);
        $this->assertSame(import_queue::STATUS_FAILED, $job->status);
    }
}
