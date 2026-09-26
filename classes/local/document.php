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

namespace mod_buchbinder\local;

use context_module;
use moodle_url;
use stdClass;

/**
 * A Buchbinder document: pages, sources, overlays and their files.
 *
 * File areas (all in the module context):
 *  - source:      original uploads, itemid = source id
 *  - page:        raster image of an image page, itemid = page id
 *  - pagecontent: embedded files of an html page, itemid = page id
 *  - audio:       audio of an audio overlay, itemid = overlay id
 *  - frameimage:  image of an image frame, itemid = overlay id
 *  - clips:       regions cut out of image pages for composed pages, itemid = 0
 *  - jobfile:     uploads waiting for a background import, itemid = job id
 *  - pagemasked:  cached learner copy of a page image with burned in masks, itemid = page id
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class document {
    /** @var string File name of page images. */
    const PAGE_FILENAME = 'page.png';

    /** @var stdClass */
    protected $instance;

    /** @var context_module */
    protected $context;

    /**
     * Constructor.
     *
     * @param stdClass $instance buchbinder record
     * @param context_module $context
     */
    public function __construct(stdClass $instance, context_module $context) {
        $this->instance = $instance;
        $this->context = $context;
    }

    /**
     * Build from a course module id.
     *
     * @param int $cmid
     * @return self
     */
    public static function from_cmid(int $cmid): self {
        global $DB;
        $cm = get_coursemodule_from_id('buchbinder', $cmid, 0, false, MUST_EXIST);
        $instance = $DB->get_record('buchbinder', ['id' => $cm->instance], '*', MUST_EXIST);
        return new self($instance, context_module::instance($cm->id));
    }

    /**
     * The activity record.
     *
     * @return stdClass
     */
    public function get_instance(): stdClass {
        return $this->instance;
    }

    /**
     * The module context.
     *
     * @return context_module
     */
    public function get_context(): context_module {
        return $this->context;
    }

    // Pages.

    /**
     * All pages in reading order.
     *
     * @return stdClass[] keyed by id
     */
    public function get_pages(): array {
        global $DB;
        return $DB->get_records('buchbinder_page', ['buchbinderid' => $this->instance->id], 'sortorder ASC, id ASC');
    }

    /**
     * Pages of the published excerpt.
     *
     * @param string|null $range overrides the configured range
     * @return stdClass[] keyed by id, each with ->pagenumber
     */
    public function get_published_pages(?string $range = null): array {
        $pages = array_values($this->get_pages());
        $numbers = page_range::parse($range ?? $this->instance->pagerange, count($pages));
        $result = [];
        foreach ($numbers as $n) {
            $page = $pages[$n - 1];
            $page->pagenumber = $n;
            $result[$page->id] = $page;
        }
        return $result;
    }

    /**
     * Load a page of this document.
     *
     * @param int $pageid
     * @return stdClass
     */
    public function get_page(int $pageid): stdClass {
        global $DB;
        return $DB->get_record('buchbinder_page', ['id' => $pageid, 'buchbinderid' => $this->instance->id], '*', MUST_EXIST);
    }

    /**
     * Next free sort order.
     *
     * @return int
     */
    protected function next_sortorder(): int {
        global $DB;
        $max = $DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {buchbinder_page} WHERE buchbinderid = ?',
            [$this->instance->id]
        );
        return (int)$max + 1;
    }

    /**
     * Insert a page record.
     *
     * @param array $fields
     * @param int|null $afterpageid insert after this page instead of at the end
     * @return stdClass
     */
    protected function insert_page(array $fields, ?int $afterpageid = null): stdClass {
        global $DB;
        $page = (object)array_merge([
            'buchbinderid' => $this->instance->id,
            'sourceid' => null,
            'pagetype' => 'image',
            'content' => null,
            'contentformat' => FORMAT_HTML,
            'width' => 0,
            'height' => 0,
            'landscapelock' => 0,
            'spreadid' => null,
            'spreadside' => null,
            'pinside' => null,
            'filler' => 0,
            'pagestyle' => null,
            'timemodified' => time(),
        ], $fields);
        if ($afterpageid) {
            $after = $this->get_page($afterpageid);
            $DB->execute(
                'UPDATE {buchbinder_page} SET sortorder = sortorder + 1 WHERE buchbinderid = ? AND sortorder > ?',
                [$this->instance->id, $after->sortorder]
            );
            $page->sortorder = $after->sortorder + 1;
        } else {
            $page->sortorder = $this->next_sortorder();
        }
        $page->id = $DB->insert_record('buchbinder_page', $page);
        return $page;
    }

    /**
     * Store the raster image of a page.
     *
     * @param stdClass $page
     * @param \GdImage $img
     */
    protected function store_page_image(stdClass $page, \GdImage $img): void {
        global $DB;
        $fs = get_file_storage();
        $fs->delete_area_files($this->context->id, 'mod_buchbinder', 'page', $page->id);
        $fs->create_file_from_string([
            'contextid' => $this->context->id,
            'component' => 'mod_buchbinder',
            'filearea' => 'page',
            'itemid' => $page->id,
            'filepath' => '/',
            'filename' => self::PAGE_FILENAME,
        ], image_cleanup::to_png($img));
        $page->width = imagesx($img);
        $page->height = imagesy($img);
        $page->timemodified = time();
        $DB->update_record('buchbinder_page', $page);
    }

    /**
     * Add an image page.
     *
     * @param \GdImage $img
     * @param int|null $sourceid
     * @param array $fields additional page fields
     * @param int|null $afterpageid
     * @return stdClass
     */
    public function add_image_page(\GdImage $img, ?int $sourceid, array $fields = [], ?int $afterpageid = null): stdClass {
        $page = $this->insert_page(array_merge(['sourceid' => $sourceid, 'pagetype' => 'image'], $fields), $afterpageid);
        $this->store_page_image($page, $img);
        return $page;
    }

    /**
     * Add an html page.
     *
     * @param string $html
     * @param int|null $sourceid
     * @param int|null $afterpageid
     * @return stdClass
     */
    public function add_html_page(string $html, ?int $sourceid, ?int $afterpageid = null): stdClass {
        return $this->insert_page(['sourceid' => $sourceid, 'pagetype' => 'html', 'content' => $html,
            'width' => 1240, 'height' => 1754], $afterpageid);
    }

    /**
     * Add composed pages from a layout source. Page breaks create several pages.
     *
     * @param string $source Quarto flavoured Markdown
     * @param int|null $afterpageid
     * @return stdClass[] created pages
     */
    public function add_layout_pages(string $source, ?int $afterpageid = null): array {
        $pages = [];
        foreach (layout_renderer::split_pages($source) as $part) {
            $page = $this->insert_page(['pagetype' => 'layout', 'content' => $part, 'contentformat' => FORMAT_MARKDOWN,
                'width' => 1240, 'height' => 1754], $afterpageid);
            $afterpageid = $page->id;
            $pages[] = $page;
        }
        return $pages;
    }

    /**
     * Update a composed page. Page breaks in the source create further pages after it.
     *
     * @param int $pageid
     * @param string $source
     * @return int number of pages the source was split into
     */
    public function update_layout_page(int $pageid, string $source): int {
        global $DB;
        $page = $this->get_page($pageid);
        $parts = layout_renderer::split_pages($source);
        $DB->update_record('buchbinder_page', (object)['id' => $page->id, 'content' => array_shift($parts),
            'timemodified' => time()]);
        if ($parts) {
            $this->add_layout_pages(implode("\n\n{{< pagebreak >}}\n\n", $parts), $page->id);
        }
        return count($parts) + 1;
    }

    // Clips: regions cut out of image pages for use in layouts.

    /**
     * Cut a region out of an image page.
     *
     * @param int $pageid
     * @param float[] $box x, y, w, h relative
     * @return string file name of the clip
     */
    public function create_clip(int $pageid, array $box): string {
        $page = $this->get_page($pageid);
        $img = $page->pagetype === 'image' ? $this->load_page_image($page) : null;
        if (!$img) {
            throw new \moodle_exception('errornotimage', 'mod_buchbinder');
        }
        [$x, $y, $w, $h] = array_map(fn($v) => max(0.0, min(1.0, (float)$v)), $box);
        $iw = imagesx($img);
        $ih = imagesy($img);
        $rect = ['x' => (int)floor($x * $iw), 'y' => (int)floor($y * $ih),
            'width' => max(1, (int)round(min($w, 1 - $x) * $iw)), 'height' => max(1, (int)round(min($h, 1 - $y) * $ih))];
        $clip = imagecrop($img, $rect);
        $number = 1;
        foreach ($this->get_clips() as $file) {
            if (preg_match('/^ausschnitt-(\d+)\.png$/', $file->get_filename(), $m)) {
                $number = max($number, (int)$m[1] + 1);
            }
        }
        $filename = 'ausschnitt-' . $number . '.png';
        get_file_storage()->create_file_from_string([
            'contextid' => $this->context->id,
            'component' => 'mod_buchbinder',
            'filearea' => 'clips',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
        ], image_cleanup::to_png($clip));
        return $filename;
    }

    /**
     * All clips.
     *
     * @return \stored_file[] keyed by file name
     */
    public function get_clips(): array {
        $clips = [];
        foreach (get_file_storage()->get_area_files($this->context->id, 'mod_buchbinder', 'clips', 0, 'filename', false) as $f) {
            $clips[$f->get_filename()] = $f;
        }
        uksort($clips, 'strnatcmp');
        return $clips;
    }

    /**
     * Whether a clip exists.
     *
     * @param string $filename
     * @return bool
     */
    public function clip_exists(string $filename): bool {
        return clean_param($filename, PARAM_FILE) === $filename && get_file_storage()->file_exists(
            $this->context->id,
            'mod_buchbinder',
            'clips',
            0,
            '/',
            $filename
        );
    }

    /**
     * URL of a clip.
     *
     * @param string $filename
     * @return moodle_url
     */
    public function clip_url(string $filename): moodle_url {
        return moodle_url::make_pluginfile_url($this->context->id, 'mod_buchbinder', 'clips', 0, '/', $filename);
    }

    /**
     * Delete a clip.
     *
     * @param string $filename
     */
    public function delete_clip(string $filename): void {
        $file = get_file_storage()->get_file(
            $this->context->id,
            'mod_buchbinder',
            'clips',
            0,
            '/',
            clean_param($filename, PARAM_FILE)
        );
        if ($file) {
            $file->delete();
        }
    }

    /**
     * Update the content of an html page.
     *
     * @param int $pageid
     * @param string $html
     */
    public function update_html_page(int $pageid, string $html): void {
        global $DB;
        $page = $this->get_page($pageid);
        $page->content = $html;
        $page->timemodified = time();
        $DB->update_record('buchbinder_page', $page);
    }

    /**
     * Load the raster image of a page.
     *
     * @param stdClass $page
     * @return \GdImage|null
     */
    public function load_page_image(stdClass $page): ?\GdImage {
        $file = $this->get_page_file($page);
        return $file ? image_cleanup::load($file->get_content()) : null;
    }

    /**
     * Stored file of an image page.
     *
     * @param stdClass $page
     * @return \stored_file|null
     */
    public function get_page_file(stdClass $page): ?\stored_file {
        $fs = get_file_storage();
        return $fs->get_file($this->context->id, 'mod_buchbinder', 'page', $page->id, '/', self::PAGE_FILENAME) ?: null;
    }

    /**
     * Page image for learners: masks that cannot be revealed are burned into the
     * image, so that the solution cannot be read from the image file itself.
     *
     * The result is cached in the file area "pagemasked" and rebuilt when the page
     * or its masks change.
     *
     * @param stdClass $page
     * @return \stored_file|null
     */
    public function get_learner_page_file(stdClass $page): ?\stored_file {
        $file = $this->get_page_file($page);
        if (!$file) {
            return null;
        }
        $masks = array_values(array_filter(
            $this->get_overlays([$page->id])[$page->id],
            fn($o) => $o->overlaytype === overlay_types::MASK && empty($o->settings['revealable'])
        ));
        if (!$masks) {
            return $file;
        }
        $key = md5($file->get_contenthash() . json_encode(array_map(fn($o) => [$o->x, $o->y, $o->w, $o->h,
            $o->settings['style']], $masks)));
        $fs = get_file_storage();
        $filename = 'page-' . $key . '.png';
        if ($cached = $fs->get_file($this->context->id, 'mod_buchbinder', 'pagemasked', $page->id, '/', $filename)) {
            return $cached;
        }
        $img = image_cleanup::load($file->get_content());
        $w = imagesx($img);
        $h = imagesy($img);
        foreach ($masks as $mask) {
            $color = $mask->settings['style'] === 'black' ? 0x000000 : 0xFFFFFF;
            imagefilledrectangle(
                $img,
                (int)floor($mask->x * $w),
                (int)floor($mask->y * $h),
                (int)ceil(($mask->x + $mask->w) * $w),
                (int)ceil(($mask->y + $mask->h) * $h),
                $color
            );
        }
        $fs->delete_area_files($this->context->id, 'mod_buchbinder', 'pagemasked', $page->id);
        return $fs->create_file_from_string([
            'contextid' => $this->context->id,
            'component' => 'mod_buchbinder',
            'filearea' => 'pagemasked',
            'itemid' => $page->id,
            'filepath' => '/',
            'filename' => $filename,
        ], image_cleanup::to_png($img));
    }

    /**
     * URL of a page image (revisioned so that caches refresh after cleanup).
     *
     * @param stdClass $page
     * @return moodle_url
     */
    public function page_image_url(stdClass $page): moodle_url {
        $url = moodle_url::make_pluginfile_url(
            $this->context->id,
            'mod_buchbinder',
            'page',
            $page->id,
            '/',
            self::PAGE_FILENAME
        );
        $url->param('rev', $page->timemodified);
        return $url;
    }

    /**
     * Html of an html page, prepared for output.
     *
     * @param stdClass $page
     * @return string
     */
    public function page_html(stdClass $page): string {
        if ($page->pagetype === 'layout') {
            $renderer = new layout_renderer(fn($src) => $this->clip_exists($src) ? $this->clip_url($src)->out(false) : null);
            // The renderer purifies all text and validates the attributes it generates itself.
            return format_text(
                $renderer->render($page->content ?? ''),
                FORMAT_HTML,
                ['context' => $this->context, 'noclean' => true]
            );
        }
        $html = file_rewrite_pluginfile_urls(
            $page->content ?? '',
            'pluginfile.php',
            $this->context->id,
            'mod_buchbinder',
            'pagecontent',
            $page->id
        );
        return format_text($html, $page->contentformat, ['context' => $this->context, 'noclean' => false]);
    }

    /**
     * Apply scan optimisation to an image page.
     *
     * @param int $pageid
     * @param array $ops any of chop, deskew, shadow, split
     * @return int number of resulting pages (2 after a split)
     */
    public function cleanup_page(int $pageid, array $ops): int {
        global $DB;
        $page = $this->get_page($pageid);
        if ($page->pagetype !== 'image') {
            return 1;
        }
        $img = $this->load_page_image($page);
        if (!$img) {
            return 1;
        }
        $img = self::apply_cleanup($img, $ops);
        if (in_array('split', $ops) && !$page->landscapelock && empty($page->spreadid)) {
            $gutter = image_cleanup::find_gutter($img);
            if ($gutter !== null) {
                [$left, $right] = image_cleanup::split($img, $gutter);
                $page->spreadid = $page->id;
                $page->spreadside = 'left';
                $this->store_page_image($page, $left);
                $this->add_image_page(
                    $right,
                    $page->sourceid,
                    ['spreadid' => $page->id, 'spreadside' => 'right'],
                    $page->id
                );
                // Coordinates of existing overlays no longer match the halves.
                $this->delete_overlays_of_page($page->id);
                return 2;
            }
        }
        $this->store_page_image($page, $img);
        if ($page->width > $page->height && !$page->landscapelock) {
            $DB->set_field('buchbinder_page', 'landscapelock', 1, ['id' => $page->id]);
        }
        return 1;
    }

    /**
     * Apply the geometric/colour cleanup steps.
     *
     * @param \GdImage $img
     * @param array $ops
     * @return \GdImage
     */
    public static function apply_cleanup(\GdImage $img, array $ops): \GdImage {
        \core_php_time_limit::raise(300);
        if (in_array('chop', $ops)) {
            $img = image_cleanup::chop_borders($img);
        }
        if (in_array('deskew', $ops)) {
            $img = image_cleanup::deskew($img);
            if (in_array('chop', $ops)) {
                $img = image_cleanup::chop_borders($img);
            }
        }
        if (in_array('shadow', $ops)) {
            $img = image_cleanup::remove_shadow($img);
        }
        return $img;
    }

    /**
     * Rotate an image page by 90 degree steps.
     *
     * @param int $pageid
     * @param int $degrees 90, 180 or 270 (clockwise)
     */
    public function rotate_page(int $pageid, int $degrees): void {
        $page = $this->get_page($pageid);
        $img = $this->load_page_image($page);
        if ($img) {
            $this->store_page_image($page, imagerotate($img, -$degrees, 0));
        }
    }

    /**
     * Toggle landscape protection.
     *
     * @param int $pageid
     * @param bool $lock
     */
    public function set_landscapelock(int $pageid, bool $lock): void {
        global $DB;
        $this->get_page($pageid);
        $DB->set_field('buchbinder_page', 'landscapelock', (int)$lock, ['id' => $pageid]);
    }

    /**
     * Separate the halves of a double page so that they can be moved independently.
     *
     * @param int $pageid
     */
    public function unlink_spread(int $pageid): void {
        global $DB;
        $page = $this->get_page($pageid);
        if ($page->spreadid) {
            $DB->execute('UPDATE {buchbinder_page} SET spreadid = NULL, spreadside = NULL
                           WHERE buchbinderid = ? AND spreadid = ?', [$this->instance->id, $page->spreadid]);
        }
        $this->rebalance();
    }

    /**
     * Link a page with its successor as left and right half of a double page.
     *
     * @param int $pageid left page
     */
    public function link_spread(int $pageid): void {
        global $DB;
        $pages = array_values(array_filter($this->get_pages(), fn($p) => !$p->filler));
        foreach ($pages as $i => $page) {
            if ($page->id == $pageid && isset($pages[$i + 1]) && !$page->spreadid && !$pages[$i + 1]->spreadid) {
                // Pins do not apply to double pages: the chain decides the sides.
                $DB->update_record('buchbinder_page', (object)['id' => $page->id, 'spreadid' => $page->id,
                    'spreadside' => 'left', 'pinside' => null]);
                $DB->update_record('buchbinder_page', (object)['id' => $pages[$i + 1]->id, 'spreadid' => $page->id,
                    'spreadside' => 'right', 'pinside' => null]);
                // Automatic blank pages between the halves are no longer needed.
                $this->rebalance();
                return;
            }
        }
    }

    /**
     * Pin a page to a left or right page of the booklet.
     *
     * Pinned pages keep their side when pages before them are added, removed or moved: the
     * document inserts or removes automatic blank pages instead.
     *
     * @param int $pageid
     * @param string|null $side booklet::LEFT, booklet::RIGHT or null to unpin
     */
    public function set_pinside(int $pageid, ?string $side): void {
        global $DB;
        $page = $this->get_page($pageid);
        if ($side !== null && !in_array($side, [booklet::LEFT, booklet::RIGHT], true)) {
            throw new \invalid_parameter_exception('Unknown side');
        }
        if ($page->spreadid && $side !== null) {
            // Double pages are aligned by their chain.
            $side = null;
        }
        $DB->set_field('buchbinder_page', 'pinside', $side, ['id' => $page->id]);
        $this->rebalance();
    }

    /**
     * Side a page must lie on, if any.
     *
     * @param stdClass $page
     * @return string|null
     */
    public static function required_side(stdClass $page): ?string {
        if ($page->spreadid && in_array($page->spreadside, [booklet::LEFT, booklet::RIGHT], true)) {
            return $page->spreadside;
        }
        return in_array($page->pinside ?? null, [booklet::LEFT, booklet::RIGHT], true) ? $page->pinside : null;
    }

    /**
     * Insert or remove automatic blank pages so that pinned pages and double pages lie on their side.
     *
     * Automatic blank pages (filler) are only removed while they are empty; as soon as something is
     * placed on them they become ordinary pages.
     *
     * @return int change of the page count
     */
    public function rebalance(): int {
        global $DB;
        $pages = array_values($this->get_pages());
        $used = [];
        if ($pages) {
            [$insql, $params] = $DB->get_in_or_equal(array_map(fn($p) => $p->id, $pages));
            $used = array_flip($DB->get_fieldset_select('buchbinder_overlay', 'DISTINCT pageid', "pageid $insql", $params));
        }
        $pool = [];
        $keep = [];
        foreach ($pages as $page) {
            if ($page->filler && $page->pagetype === 'canvas' && !isset($used[$page->id])) {
                $pool[] = $page;
            } else {
                $keep[] = $page;
            }
        }
        $firstright = $this->first_page_right();
        $order = [];
        $position = 1;
        foreach ($keep as $page) {
            $required = self::required_side($page);
            if ($required !== null && booklet::side($position, $firstright) !== $required) {
                $order[] = array_shift($pool) ?? null;
                $position++;
            }
            $order[] = $page;
            $position++;
        }
        $before = count($pages);
        $transaction = $DB->start_delegated_transaction();
        foreach ($pool as $unused) {
            $DB->delete_records('buchbinder_page', ['id' => $unused->id]);
        }
        $sortorder = 1;
        foreach ($order as $page) {
            if ($page === null) {
                $page = $this->insert_page(['pagetype' => 'canvas', 'width' => booklet::CANVAS_WIDTH,
                    'height' => booklet::CANVAS_HEIGHT, 'filler' => 1, 'sortorder' => $sortorder]);
                $DB->set_field('buchbinder_page', 'sortorder', $sortorder, ['id' => $page->id]);
            } else if ((int)$page->sortorder !== $sortorder) {
                $DB->set_field('buchbinder_page', 'sortorder', $sortorder, ['id' => $page->id]);
            }
            $sortorder++;
        }
        $transaction->allow_commit();
        return count($order) - $before;
    }

    /**
     * Move a page (or the double page it belongs to) one step.
     *
     * Double pages always move as a unit so that left and right halves stay in place.
     *
     * @param int $pageid
     * @param int $direction -1 up, 1 down
     */
    public function move_page(int $pageid, int $direction): void {
        global $DB;
        $units = [];
        $current = null;
        foreach ($this->get_pages() as $page) {
            if ($page->filler && $page->id != $pageid) {
                // Automatic blank pages are recreated by rebalance().
                continue;
            }
            $key = $page->spreadid ? 's' . $page->spreadid : 'p' . $page->id;
            if (!isset($units[$key])) {
                $units[$key] = [];
            }
            $units[$key][] = $page->id;
            if ($page->id == $pageid) {
                $current = $key;
            }
        }
        if ($current === null) {
            return;
        }
        $keys = array_keys($units);
        $pos = array_search($current, $keys);
        $target = $pos + ($direction < 0 ? -1 : 1);
        if ($target < 0 || $target >= count($keys)) {
            return;
        }
        [$keys[$pos], $keys[$target]] = [$keys[$target], $keys[$pos]];
        $order = 1;
        $transaction = $DB->start_delegated_transaction();
        foreach ($keys as $key) {
            foreach ($units[$key] as $id) {
                $DB->set_field('buchbinder_page', 'sortorder', $order++, ['id' => $id]);
            }
        }
        $transaction->allow_commit();
        $this->rebalance();
    }

    /**
     * Delete a page including overlays and files.
     *
     * @param int $pageid
     * @param bool $rebalance restore the sides of pinned pages and double pages afterwards
     */
    public function delete_page(int $pageid, bool $rebalance = true): void {
        global $DB;
        $page = $this->get_page($pageid);
        $this->delete_overlays_of_page($page->id);
        $fs = get_file_storage();
        $fs->delete_area_files($this->context->id, 'mod_buchbinder', 'page', $page->id);
        $fs->delete_area_files($this->context->id, 'mod_buchbinder', 'pagecontent', $page->id);
        $fs->delete_area_files($this->context->id, 'mod_buchbinder', 'pagemasked', $page->id);
        $DB->delete_records('buchbinder_page', ['id' => $page->id]);
        if ($page->spreadid) {
            $this->unlink_spread_by_id((int)$page->spreadid);
        }
        if ($rebalance) {
            $this->rebalance();
        }
    }

    /**
     * Remove the spread relation of the remaining half.
     *
     * @param int $spreadid
     */
    protected function unlink_spread_by_id(int $spreadid): void {
        global $DB;
        $DB->execute(
            'UPDATE {buchbinder_page} SET spreadid = NULL, spreadside = NULL WHERE buchbinderid = ? AND spreadid = ?',
            [$this->instance->id, $spreadid]
        );
    }

    // Booklet: positions, sides, blank pages.

    /**
     * Whether page 1 is a right page (printed book convention).
     *
     * @return bool
     */
    public function first_page_right(): bool {
        return !isset($this->instance->firstpageright) || (bool)$this->instance->firstpageright;
    }

    /**
     * Positions (1-based) of all pages.
     *
     * @return int[] pageid => position
     */
    public function get_positions(): array {
        $positions = [];
        $i = 1;
        foreach ($this->get_pages() as $page) {
            $positions[$page->id] = $i++;
        }
        return $positions;
    }

    /**
     * Side of the page at a position.
     *
     * @param int $position
     * @return string booklet::LEFT or booklet::RIGHT
     */
    public function side_at(int $position): string {
        return booklet::side($position, $this->first_page_right());
    }

    /**
     * Add an empty canvas page (white page for frames).
     *
     * @param int|null $afterpageid insert after this page, null: append
     * @param array $fields additional page fields
     * @return stdClass
     */
    public function add_canvas_page(?int $afterpageid = null, array $fields = []): stdClass {
        return $this->insert_page(array_merge(['pagetype' => 'canvas', 'width' => booklet::CANVAS_WIDTH,
            'height' => booklet::CANVAS_HEIGHT], $fields), $afterpageid);
    }

    /**
     * Insert a blank page before or after a page.
     *
     * Double pages are never torn apart: the page goes before the left or after the right half.
     *
     * @param int $pageid
     * @param bool $before
     * @return stdClass new page
     */
    public function insert_blank_page(int $pageid, bool $before = false): stdClass {
        global $DB;
        $pages = array_values($this->get_pages());
        $index = array_search($pageid, array_map(fn($p) => (int)$p->id, $pages));
        if ($index === false) {
            throw new \invalid_parameter_exception('Unknown page');
        }
        $page = $pages[$index];
        if ($page->spreadid) {
            foreach ($pages as $i => $other) {
                if ($other->spreadid == $page->spreadid && $other->spreadside === ($before ? 'left' : 'right')) {
                    $index = $i;
                }
            }
        }
        if (!$before) {
            $new = $this->add_canvas_page((int)$pages[$index]->id);
        } else if ($index === 0) {
            // Insert at the very beginning.
            $DB->execute(
                'UPDATE {buchbinder_page} SET sortorder = sortorder + 1 WHERE buchbinderid = ?',
                [$this->instance->id]
            );
            $new = $this->insert_page(['pagetype' => 'canvas', 'width' => booklet::CANVAS_WIDTH,
                'height' => booklet::CANVAS_HEIGHT, 'sortorder' => 0]);
            $DB->set_field('buchbinder_page', 'sortorder', $pages[0]->sortorder, ['id' => $new->id]);
        } else {
            $new = $this->add_canvas_page((int)$pages[$index - 1]->id);
        }
        $this->rebalance();
        return $new;
    }

    /**
     * Pages that do not lie on their side (pinned pages, torn double pages).
     *
     * After rebalance() this is normally empty.
     *
     * @return array[] ['pageid' => int, 'position' => int, 'side' => string required side]
     */
    public function layout_issues(): array {
        $issues = [];
        $pages = array_values($this->get_pages());
        foreach ($pages as $i => $page) {
            $required = self::required_side($page);
            $position = $i + 1;
            $torn = false;
            if ($page->spreadid && $page->spreadside === booklet::LEFT) {
                $next = $pages[$i + 1] ?? null;
                $torn = !$next || $next->spreadid != $page->spreadid;
            }
            if ($torn || ($required !== null && $this->side_at($position) !== $required)) {
                $issues[] = ['pageid' => (int)$page->id, 'position' => $position, 'side' => (string)$required];
            }
        }
        return $issues;
    }

    // Frames.

    /**
     * Store the image of an image frame.
     *
     * @param int $overlayid
     * @param string $filename
     * @param string $content
     * @return stdClass overlay
     */
    public function save_frame_image(int $overlayid, string $filename, string $content): stdClass {
        global $DB;
        $overlay = $this->get_overlay($overlayid);
        if ($overlay->overlaytype !== overlay_types::IMAGEFRAME) {
            throw new \invalid_parameter_exception('Not an image frame');
        }
        $maxbytes = self::max_bytes($this->context);
        if ($maxbytes > 0 && strlen($content) > $maxbytes) {
            throw new \moodle_exception('errorfiletoolarge', 'mod_buchbinder', '', display_size($maxbytes));
        }
        $info = @getimagesizefromstring($content);
        if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_WEBP])) {
            throw new \moodle_exception('errorimage', 'mod_buchbinder');
        }
        $filename = pathinfo(clean_filename($filename) ?: 'image', PATHINFO_FILENAME) . image_type_to_extension($info[2]);
        $fs = get_file_storage();
        $fs->delete_area_files($this->context->id, 'mod_buchbinder', 'frameimage', $overlay->id);
        $fs->create_file_from_string([
            'contextid' => $this->context->id,
            'component' => 'mod_buchbinder',
            'filearea' => 'frameimage',
            'itemid' => $overlay->id,
            'filepath' => '/',
            'filename' => $filename,
        ], $content);
        $overlay->settings['filename'] = $filename;
        $DB->update_record('buchbinder_overlay', (object)['id' => $overlay->id, 'data' => json_encode($overlay->settings),
            'timemodified' => time()]);
        return $overlay;
    }

    /**
     * URL of the image of an image frame.
     *
     * @param stdClass $overlay
     * @return moodle_url|null
     */
    public function frame_image_url(stdClass $overlay): ?moodle_url {
        if (empty($overlay->settings['filename'])) {
            return null;
        }
        return moodle_url::make_pluginfile_url(
            $this->context->id,
            'mod_buchbinder',
            'frameimage',
            $overlay->id,
            '/',
            $overlay->settings['filename']
        );
    }

    /**
     * Stored file of an image frame.
     *
     * @param stdClass $overlay
     * @return \stored_file|null
     */
    public function frame_image_file(stdClass $overlay): ?\stored_file {
        if (empty($overlay->settings['filename'])) {
            return null;
        }
        return get_file_storage()->get_file(
            $this->context->id,
            'mod_buchbinder',
            'frameimage',
            $overlay->id,
            '/',
            $overlay->settings['filename']
        ) ?: null;
    }

    // Overlays.

    /**
     * Overlays of the given pages.
     *
     * @param int[] $pageids
     * @return array pageid => stdClass[] with decoded ->settings
     */
    public function get_overlays(array $pageids): array {
        global $DB;
        $result = array_fill_keys($pageids, []);
        if (!$pageids) {
            return $result;
        }
        [$insql, $params] = $DB->get_in_or_equal($pageids);
        $records = $DB->get_records_select('buchbinder_overlay', "pageid $insql", $params, 'sortorder ASC, id ASC');
        foreach ($records as $overlay) {
            $overlay->settings = json_decode($overlay->data ?? '', true) ?: [];
            $result[$overlay->pageid][] = $overlay;
        }
        return $result;
    }

    /**
     * Load an overlay of this document.
     *
     * @param int $overlayid
     * @return stdClass
     */
    public function get_overlay(int $overlayid): stdClass {
        global $DB;
        $overlay = $DB->get_record('buchbinder_overlay', ['id' => $overlayid], '*', MUST_EXIST);
        $this->get_page((int)$overlay->pageid);
        $overlay->settings = json_decode($overlay->data ?? '', true) ?: [];
        return $overlay;
    }

    /**
     * Create or update an overlay.
     *
     * @param int $pageid
     * @param int $overlayid 0 to create
     * @param string $type
     * @param float[] $box x, y, w, h relative to the page (0..1)
     * @param array $settings
     * @return stdClass
     */
    public function save_overlay(int $pageid, int $overlayid, string $type, array $box, array $settings): stdClass {
        global $DB;
        $page = $this->get_page($pageid);
        [$x, $y, $w, $h] = array_map(fn($v) => max(0.0, min(1.0, (float)$v)), $box);
        $w = min($w, 1 - $x);
        $h = min($h, 1 - $y);
        if ($overlayid) {
            $overlay = $this->get_overlay($overlayid);
            if ($overlay->overlaytype !== $type) {
                throw new \invalid_parameter_exception('Overlay does not match type');
            }
            // Frames may be moved to another page of the document (e.g. across a double page).
            $overlay->pageid = $page->id;
            if (in_array($type, [overlay_types::AUDIO, overlay_types::IMAGEFRAME])) {
                // The file name is set by save_audio() / save_frame_image() only.
                $settings['filename'] = $overlay->settings['filename'] ?? '';
            }
        } else {
            $overlay = (object)['pageid' => $page->id, 'overlaytype' => $type];
            $settings['filename'] = '';
        }
        if ($page->filler) {
            // Content turns an automatic blank page into an ordinary page.
            $DB->set_field('buchbinder_page', 'filler', 0, ['id' => $page->id]);
        }
        $overlay->settings = overlay_types::clean($type, $settings);
        $overlay->data = json_encode($overlay->settings);
        $overlay->x = $x;
        $overlay->y = $y;
        $overlay->w = $w;
        $overlay->h = $h;
        $overlay->timemodified = time();
        if (!empty($overlay->id)) {
            $DB->update_record('buchbinder_overlay', $overlay);
        } else {
            $overlay->sortorder = (int)$DB->get_field_sql(
                'SELECT MAX(sortorder) FROM {buchbinder_overlay} WHERE pageid = ?',
                [$page->id]
            ) + 1;
            $overlay->id = $DB->insert_record('buchbinder_overlay', $overlay);
        }
        return $overlay;
    }

    /**
     * Attach audio to an audio overlay.
     *
     * @param int $overlayid
     * @param string $filename
     * @param string $content
     * @return stdClass overlay
     */
    public function save_audio(int $overlayid, string $filename, string $content): stdClass {
        global $DB;
        $overlay = $this->get_overlay($overlayid);
        if ($overlay->overlaytype !== overlay_types::AUDIO) {
            throw new \invalid_parameter_exception('Not an audio overlay');
        }
        $maxbytes = self::max_bytes($this->context);
        if ($maxbytes > 0 && strlen($content) > $maxbytes) {
            throw new \moodle_exception('errorfiletoolarge', 'mod_buchbinder', '', display_size($maxbytes));
        }
        $filename = clean_filename($filename) ?: 'audio.webm';
        $mimetype = mimeinfo('type', $filename);
        if (strpos($mimetype, 'audio/') !== 0 && strpos($mimetype, 'video/webm') !== 0) {
            throw new \moodle_exception('erroraudiotype', 'mod_buchbinder');
        }
        $fs = get_file_storage();
        $fs->delete_area_files($this->context->id, 'mod_buchbinder', 'audio', $overlay->id);
        $fs->create_file_from_string([
            'contextid' => $this->context->id,
            'component' => 'mod_buchbinder',
            'filearea' => 'audio',
            'itemid' => $overlay->id,
            'filepath' => '/',
            'filename' => $filename,
        ], $content);
        $overlay->settings['filename'] = $filename;
        $overlay->settings['mode'] = 'file';
        $DB->update_record('buchbinder_overlay', (object)['id' => $overlay->id, 'data' => json_encode($overlay->settings),
            'timemodified' => time()]);
        return $overlay;
    }

    /**
     * URL of the audio of an overlay.
     *
     * @param stdClass $overlay
     * @return moodle_url|null
     */
    public function audio_url(stdClass $overlay): ?moodle_url {
        if (empty($overlay->settings['filename'])) {
            return null;
        }
        return moodle_url::make_pluginfile_url(
            $this->context->id,
            'mod_buchbinder',
            'audio',
            $overlay->id,
            '/',
            $overlay->settings['filename']
        );
    }

    /**
     * Delete an overlay.
     *
     * @param int $overlayid
     */
    public function delete_overlay(int $overlayid): void {
        global $DB;
        $overlay = $this->get_overlay($overlayid);
        $fs = get_file_storage();
        foreach (['audio', 'frameimage'] as $area) {
            $fs->delete_area_files($this->context->id, 'mod_buchbinder', $area, $overlay->id);
        }
        $DB->delete_records('buchbinder_overlay', ['id' => $overlay->id]);
    }

    /**
     * Delete all overlays of a page.
     *
     * @param int $pageid
     */
    protected function delete_overlays_of_page(int $pageid): void {
        global $DB;
        $fs = get_file_storage();
        foreach ($DB->get_fieldset_select('buchbinder_overlay', 'id', 'pageid = ?', [$pageid]) as $id) {
            $fs->delete_area_files($this->context->id, 'mod_buchbinder', 'audio', $id);
            $fs->delete_area_files($this->context->id, 'mod_buchbinder', 'frameimage', $id);
        }
        $DB->delete_records('buchbinder_overlay', ['pageid' => $pageid]);
    }

    // Sources.

    /**
     * Record the provenance of imported content.
     *
     * @param string $type
     * @param array $fields title, url, author, filename, timeaccessed
     * @return int source id
     */
    public function add_source(string $type, array $fields = []): int {
        global $DB;
        $record = (object)[
            'buchbinderid' => $this->instance->id,
            'sourcetype' => $type,
            'title' => \core_text::substr(trim($fields['title'] ?? ''), 0, 1333),
            'url' => $fields['url'] ?? null,
            'author' => \core_text::substr(trim($fields['author'] ?? ''), 0, 255),
            'filename' => $fields['filename'] ?? null,
            'timeaccessed' => $fields['timeaccessed'] ?? time(),
            'showcitation' => isset($fields['showcitation']) ? (int)$fields['showcitation'] : 1,
            'timecreated' => time(),
        ];
        return $DB->insert_record('buchbinder_source', $record);
    }

    /**
     * Sources of this document.
     *
     * @return stdClass[]
     */
    public function get_sources(): array {
        global $DB;
        return $DB->get_records('buchbinder_source', ['buchbinderid' => $this->instance->id], 'id ASC');
    }

    /**
     * Update the citation data of a source.
     *
     * @param stdClass $data
     */
    public function update_source(stdClass $data): void {
        global $DB;
        $source = $DB->get_record(
            'buchbinder_source',
            ['id' => $data->id, 'buchbinderid' => $this->instance->id],
            '*',
            MUST_EXIST
        );
        foreach (['title', 'url', 'author', 'timeaccessed', 'showcitation'] as $field) {
            if (isset($data->$field)) {
                $source->$field = $data->$field;
            }
        }
        $DB->update_record('buchbinder_source', $source);
    }

    /**
     * Delete a source, optionally with all pages imported from it.
     *
     * @param int $sourceid
     * @param bool $deletepages
     * @return int number of deleted pages
     */
    public function delete_source(int $sourceid, bool $deletepages): int {
        global $DB;
        $source = $DB->get_record(
            'buchbinder_source',
            ['id' => $sourceid, 'buchbinderid' => $this->instance->id],
            '*',
            MUST_EXIST
        );
        $count = 0;
        foreach (
            $DB->get_fieldset_select(
                'buchbinder_page',
                'id',
                'buchbinderid = ? AND sourceid = ?',
                [$this->instance->id, $source->id]
            ) as $pageid
        ) {
            if ($deletepages) {
                $this->delete_page((int)$pageid, false);
                $count++;
            } else {
                $DB->set_field('buchbinder_page', 'sourceid', null, ['id' => $pageid]);
            }
        }
        get_file_storage()->delete_area_files($this->context->id, 'mod_buchbinder', 'source', $source->id);
        $DB->delete_records('buchbinder_source', ['id' => $source->id]);
        if ($count) {
            $this->rebalance();
        }
        return $count;
    }

    /**
     * Whether a citation is shown below pages of this source.
     *
     * @param stdClass $source
     * @return bool
     */
    public static function has_citation(stdClass $source): bool {
        return $source->showcitation && (!empty($source->url) || !empty($source->author));
    }

    /**
     * Citation line of a source for the page footer.
     *
     * @param stdClass $source
     * @return string html
     */
    public static function citation(stdClass $source): string {
        $parts = [];
        if (!empty($source->author)) {
            $parts[] = s($source->author);
        }
        $title = $source->title ?: $source->filename;
        if (!empty($source->url)) {
            $parts[] = \html_writer::link($source->url, s($title ?: $source->url), ['target' => '_blank', 'rel' => 'noopener']);
        } else if ($title) {
            $parts[] = s($title);
        }
        if (!empty($source->url) && $source->timeaccessed) {
            $parts[] = get_string(
                'citationaccessed',
                'mod_buchbinder',
                userdate($source->timeaccessed, get_string('strftimedate', 'langconfig'))
            );
        }
        return implode(', ', $parts);
    }

    // Asset bank.

    /**
     * Copy pages of another document into this one (asset bank excerpt).
     *
     * @param document $master
     * @param string $range page range of the master
     * @return int number of copied pages
     */
    public function copy_pages_from(document $master, string $range): int {
        global $DB;
        $fs = get_file_storage();
        $pages = $master->get_published_pages($range);
        $overlays = $master->get_overlays(array_keys($pages));
        $sourcemap = [];
        $spreadmap = [];
        $count = 0;
        foreach ($pages as $page) {
            if ($page->sourceid && !isset($sourcemap[$page->sourceid])) {
                $source = $DB->get_record('buchbinder_source', ['id' => $page->sourceid]);
                $sourcemap[$page->sourceid] = $source ? $this->add_source($source->sourcetype, (array)$source) : null;
            }
            $newpage = clone $page;
            unset($newpage->id, $newpage->pagenumber, $newpage->buchbinderid, $newpage->sortorder);
            $newpage->sourceid = $page->sourceid ? $sourcemap[$page->sourceid] : null;
            $newpage->spreadid = null;
            $new = $this->insert_page((array)$newpage);
            if ($page->spreadid) {
                // Keep double pages together if both halves are part of the excerpt.
                if (isset($spreadmap[$page->spreadid])) {
                    $DB->update_record('buchbinder_page', (object)['id' => $new->id,
                        'spreadid' => $spreadmap[$page->spreadid]]);
                } else {
                    $spreadmap[$page->spreadid] = $new->id;
                    $DB->update_record('buchbinder_page', (object)['id' => $new->id, 'spreadid' => $new->id]);
                }
            }
            foreach (['page', 'pagecontent'] as $area) {
                foreach ($fs->get_area_files($master->get_context()->id, 'mod_buchbinder', $area, $page->id, 'id', false) as $f) {
                    $fs->create_file_from_storedfile(['contextid' => $this->context->id, 'itemid' => $new->id], $f);
                }
            }
            foreach ($overlays[$page->id] as $overlay) {
                $oldid = $overlay->id;
                unset($overlay->id, $overlay->settings);
                $overlay->pageid = $new->id;
                $newid = $DB->insert_record('buchbinder_overlay', $overlay);
                $files = array_merge(
                    $fs->get_area_files($master->get_context()->id, 'mod_buchbinder', 'audio', $oldid, 'id', false),
                    $fs->get_area_files($master->get_context()->id, 'mod_buchbinder', 'frameimage', $oldid, 'id', false)
                );
                foreach ($files as $f) {
                    $fs->create_file_from_storedfile(['contextid' => $this->context->id, 'itemid' => $newid], $f);
                }
            }
            $count++;
        }
        // Clips used by composed pages; existing clips of the same name are kept.
        foreach ($master->get_clips() as $filename => $clip) {
            if (!$this->clip_exists($filename)) {
                $fs->create_file_from_storedfile(['contextid' => $this->context->id], $clip);
            }
        }
        // Remove spread links whose partner was not copied.
        foreach ($spreadmap as $spreadid) {
            if ($DB->count_records('buchbinder_page', ['spreadid' => $spreadid]) < 2) {
                $this->unlink_spread_by_id($spreadid);
            }
        }
        $DB->update_record('buchbinder', (object)['id' => $this->instance->id, 'masterid' => $master->get_instance()->id,
            'masterrange' => $range, 'timemodified' => time()]);
        $this->rebalance();
        return $count;
    }

    /**
     * Delete everything that belongs to this document.
     */
    public function delete_all(): void {
        global $DB;
        $pageids = array_keys($this->get_pages());
        if ($pageids) {
            [$insql, $params] = $DB->get_in_or_equal($pageids);
            $DB->delete_records_select('buchbinder_overlay', "pageid $insql", $params);
        }
        $DB->delete_records('buchbinder_page', ['buchbinderid' => $this->instance->id]);
        $DB->delete_records('buchbinder_source', ['buchbinderid' => $this->instance->id]);
        get_file_storage()->delete_area_files($this->context->id, 'mod_buchbinder');
    }

    /**
     * Configured maximum size of imports and audio files.
     *
     * @param \context $context
     * @return int bytes
     */
    public static function max_bytes(\context $context): int {
        global $CFG;
        $configured = (int)get_config('buchbinder', 'maxbytes');
        return get_user_max_upload_file_size($context, $CFG->maxbytes, 0, $configured);
    }
}
