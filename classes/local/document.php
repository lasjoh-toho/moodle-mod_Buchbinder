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
    }

    /**
     * Link a page with its successor as left and right half of a double page.
     *
     * @param int $pageid left page
     */
    public function link_spread(int $pageid): void {
        global $DB;
        $pages = array_values($this->get_pages());
        foreach ($pages as $i => $page) {
            if ($page->id == $pageid && isset($pages[$i + 1]) && !$page->spreadid && !$pages[$i + 1]->spreadid) {
                $DB->update_record('buchbinder_page', (object)['id' => $page->id, 'spreadid' => $page->id,
                    'spreadside' => 'left']);
                $DB->update_record('buchbinder_page', (object)['id' => $pages[$i + 1]->id, 'spreadid' => $page->id,
                    'spreadside' => 'right']);
                return;
            }
        }
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
    }

    /**
     * Delete a page including overlays and files.
     *
     * @param int $pageid
     */
    public function delete_page(int $pageid): void {
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
            if ($overlay->pageid != $page->id || $overlay->overlaytype !== $type) {
                throw new \invalid_parameter_exception('Overlay does not match page or type');
            }
            if ($type === overlay_types::AUDIO) {
                // The file name is set by save_audio only.
                $settings['filename'] = $overlay->settings['filename'] ?? '';
            }
        } else {
            $overlay = (object)['pageid' => $page->id, 'overlaytype' => $type];
            $settings['filename'] = '';
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
        get_file_storage()->delete_area_files($this->context->id, 'mod_buchbinder', 'audio', $overlay->id);
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
                foreach ($fs->get_area_files($master->get_context()->id, 'mod_buchbinder', 'audio', $oldid, 'id', false) as $f) {
                    $fs->create_file_from_storedfile(['contextid' => $this->context->id, 'itemid' => $newid], $f);
                }
            }
            $count++;
        }
        // Remove spread links whose partner was not copied.
        foreach ($spreadmap as $spreadid) {
            if ($DB->count_records('buchbinder_page', ['spreadid' => $spreadid]) < 2) {
                $this->unlink_spread_by_id($spreadid);
            }
        }
        $DB->update_record('buchbinder', (object)['id' => $this->instance->id, 'masterid' => $master->get_instance()->id,
            'masterrange' => $range, 'timemodified' => time()]);
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
