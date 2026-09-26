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

/**
 * Layout flow: places content blocks as text and image frames on booklet pages.
 *
 * Text is collected into one text frame per page and column of the type area; images get their own
 * frames. When a page is full, a new page is added (its type area is mirrored for left and right
 * pages). Long paragraphs, lists and tables are split between pages, headings are kept with the
 * following text. Heights are estimated from the text length with the metrics the viewer and the
 * print engine use (11 pt body text, line height 1.4 on an A4 page), so the frames can be adjusted
 * afterwards in the studio.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flow {
    /** @var float Body text size in mm (11 pt). */
    const FONT_MM = 3.88;
    /** @var float Line height factor. */
    const LINE_HEIGHT = 1.4;
    /** @var float Average character width relative to the font size. */
    const CHAR_WIDTH = 0.5;
    /** @var float Space between frames in mm. */
    const GAP_MM = 4;
    /** @var array Size factors of headings. */
    const HEADING_SCALE = ['h1' => 1.8, 'h2' => 1.45, 'h3' => 1.2, 'h4' => 1.05, 'h5' => 1, 'h6' => 1];

    /** @var document */
    protected $document;

    /** @var int|null */
    protected $sourceid;

    /** @var \stdClass|null current page */
    protected $page = null;

    /** @var float[] type area of the current page */
    protected $area;

    /** @var float current vertical position (relative) */
    protected $y;

    /** @var string[] html of the text frame being filled */
    protected $chunk = [];

    /** @var float top of the text frame being filled */
    protected $chunktop;

    /** @var \stdClass[] created pages */
    protected $pages = [];

    /** @var bool start a new page before the next content */
    protected $breakpending = false;

    /**
     * Constructor.
     *
     * @param document $document
     * @param int|null $sourceid
     */
    public function __construct(document $document, ?int $sourceid) {
        $this->document = $document;
        $this->sourceid = $sourceid;
    }

    /**
     * Place blocks on new pages at the end of the document.
     *
     * @param array $blocks see blocks
     * @param bool $startright start on a right page (adds a blank page if needed)
     * @return \stdClass[] created pages (without blank padding pages)
     */
    public function run(array $blocks, bool $startright = false): array {
        if ($startright) {
            $this->document->pad_to_side(booklet::RIGHT);
        }
        foreach ($blocks as $block) {
            if ($block['type'] === 'pagebreak') {
                $this->breakpending = $this->page !== null;
            } else if ($block['type'] === 'image') {
                $this->place_image($block);
            } else {
                $this->place_text($block['tag'], $block['html']);
            }
        }
        $this->flush();
        return $this->pages;
    }

    // Geometry.

    /**
     * Height of one body text line relative to the page height.
     *
     * @return float
     */
    public static function line_height(): float {
        return self::FONT_MM * self::LINE_HEIGHT / booklet::PAGE_HEIGHT_MM;
    }

    /**
     * Characters per line in a frame of the given relative width.
     *
     * @param float $width relative to the page width
     * @param float $scale font scale
     * @return int
     */
    public static function chars_per_line(float $width, float $scale = 1.0): int {
        return max(10, (int)floor($width * booklet::PAGE_WIDTH_MM / (self::FONT_MM * self::CHAR_WIDTH * $scale)));
    }

    /**
     * Estimated height of a text block relative to the page height.
     *
     * @param string $tag
     * @param string $html
     * @param float $width relative width of the frame
     * @return float
     */
    public static function estimate(string $tag, string $html, float $width): float {
        $cpl = self::chars_per_line($width);
        $text = fn($h) => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($h), ENT_QUOTES, 'UTF-8')));
        $lines = 0.0;
        if (isset(self::HEADING_SCALE[$tag])) {
            $scale = self::HEADING_SCALE[$tag];
            $lines = ceil(\core_text::strlen($text($html)) / self::chars_per_line($width, $scale)) * $scale + 0.8;
        } else if ($tag === 'ul' || $tag === 'ol' || $tag === 'dl') {
            preg_match_all('#<(li|dt|dd)\b.*?</\1>#su', $html, $items);
            foreach ($items[0] ?: [$html] as $item) {
                $lines += max(1, ceil(\core_text::strlen($text($item)) / max(10, $cpl - 4)));
            }
            $lines += 0.6;
        } else if ($tag === 'table') {
            preg_match_all('#<tr\b.*?</tr>#su', $html, $rows);
            foreach ($rows[0] as $row) {
                preg_match_all('#<t[dh]\b.*?</t[dh]>#su', $row, $cells);
                $cols = max(1, count($cells[0]));
                $max = 1;
                foreach ($cells[0] as $cell) {
                    $max = max($max, ceil(\core_text::strlen($text($cell)) / max(4, $cpl / $cols - 2)));
                }
                $lines += $max + 0.4;
            }
            $lines += 0.8;
        } else if ($tag === 'pre') {
            $lines = substr_count(strip_tags($html), "\n") + 1.6;
        } else {
            $lines = max(1, ceil(\core_text::strlen($text($html)) / $cpl)) + 0.6;
        }
        return $lines * self::line_height() * 1.05;
    }

    // Placement.

    /**
     * Start a new page.
     */
    protected function new_page(): void {
        $this->flush();
        $this->page = $this->document->add_canvas_page(null, ['sourceid' => $this->sourceid]);
        $this->pages[] = $this->page;
        $position = count($this->document->get_pages());
        $this->area = booklet::type_area($this->document->side_at($position));
        $this->y = $this->area[1];
        $this->breakpending = false;
    }

    /**
     * Bottom of the type area.
     *
     * @return float
     */
    protected function bottom(): float {
        return $this->area[1] + $this->area[3];
    }

    /**
     * Make sure there is a page with at least the given free height (or a fresh page).
     *
     * @param float $height
     */
    protected function ensure_space(float $height): void {
        if ($this->page === null || $this->breakpending) {
            $this->new_page();
        } else if ($this->y + $height > $this->bottom() + 1e-9 && $this->y > $this->area[1] + 1e-9) {
            $this->new_page();
        }
    }

    /**
     * Place a text block, splitting it if it does not fit.
     *
     * @param string $tag
     * @param string $html
     */
    protected function place_text(string $tag, string $html): void {
        $guard = 0;
        while ($html !== '' && $guard++ < 200) {
            $width = $this->area[2] ?? booklet::type_area(booklet::RIGHT)[2];
            $height = self::estimate($tag, $html, $width);
            // Keep headings with at least three lines of the following text.
            $needed = isset(self::HEADING_SCALE[$tag]) ? $height + 3 * self::line_height() : $height;
            if ($this->page !== null && !$this->breakpending && $this->y + $needed <= $this->bottom() + 1e-9) {
                $this->add_to_chunk($html, $height);
                return;
            }
            if ($this->page === null || $this->breakpending) {
                $this->new_page();
                continue;
            }
            $free = $this->bottom() - $this->y;
            if (!isset(self::HEADING_SCALE[$tag]) && $free >= 3 * self::line_height()) {
                [$first, $rest] = self::split($tag, $html, $free, $width);
                if ($first !== '') {
                    $this->add_to_chunk($first, self::estimate($tag, $first, $width));
                    $html = $rest;
                }
            }
            if ($this->y <= $this->area[1] + 1e-9) {
                // Even an empty page is too small: place it anyway, the frame can be adjusted in the studio.
                $this->add_to_chunk($html, min($height, $this->area[3]));
                return;
            }
            $this->new_page();
        }
    }

    /**
     * Add html to the current text frame.
     *
     * @param string $html
     * @param float $height
     */
    protected function add_to_chunk(string $html, float $height): void {
        if (!$this->chunk) {
            $this->chunktop = $this->y;
        }
        $this->chunk[] = $html;
        $this->y += $height;
    }

    /**
     * Save the text frame being filled.
     */
    protected function flush(): void {
        if (!$this->chunk || !$this->page) {
            $this->chunk = [];
            return;
        }
        $height = min($this->y, $this->bottom()) - $this->chunktop;
        $this->document->save_overlay(
            (int)$this->page->id,
            0,
            overlay_types::TEXTFRAME,
            [$this->area[0], $this->chunktop, $this->area[2], max($height, self::line_height())],
            ['html' => implode("\n", $this->chunk)]
        );
        $this->chunk = [];
        $this->y += self::GAP_MM / booklet::PAGE_HEIGHT_MM;
    }

    /**
     * Place an image block as image frame.
     *
     * @param array $block
     */
    protected function place_image(array $block): void {
        $area = $this->area ?? booklet::type_area(booklet::RIGHT);
        $aspect = booklet::PAGE_WIDTH_MM / booklet::PAGE_HEIGHT_MM;
        // Natural size at 150 dpi, at most the width of the type area.
        $width = min($area[2], $block['width'] / booklet::CANVAS_WIDTH);
        $height = $width * $block['height'] / max(1, $block['width']) * $aspect;
        if ($height > $area[3] * 0.9) {
            $height = $area[3] * 0.9;
            $width = $height / $aspect * $block['width'] / max(1, $block['height']);
        }
        $this->flush();
        $this->ensure_space($height);
        $this->flush();
        $x = $this->area[0] + ($this->area[2] - $width) / 2;
        $frame = $this->document->save_overlay(
            (int)$this->page->id,
            0,
            overlay_types::IMAGEFRAME,
            [$x, $this->y, $width, $height],
            ['alt' => $block['alt'], 'caption' => $block['caption']]
        );
        $this->document->save_frame_image((int)$frame->id, $block['filename'], $block['data']);
        $this->y += $height + self::GAP_MM / booklet::PAGE_HEIGHT_MM;
        if ($block['caption'] !== '') {
            $this->place_text('p', '<p class="bb-caption"><em>' . s($block['caption']) . '</em></p>');
        }
    }

    // Splitting.

    /**
     * Split a text block so that the first part fits into the given height.
     *
     * @param string $tag
     * @param string $html
     * @param float $height available height
     * @param float $width frame width
     * @return string[] [first part, rest]; first part may be empty
     */
    public static function split(string $tag, string $html, float $height, float $width): array {
        if ($tag === 'ul' || $tag === 'ol') {
            return self::split_children($tag, $html, $height, $width, 'li');
        }
        if ($tag === 'table') {
            return self::split_children($tag, $html, $height, $width, 'tr');
        }
        if (in_array($tag, ['p', 'blockquote'])) {
            $lines = (int)floor($height / (self::line_height() * 1.05)) - 1;
            $chars = $lines * self::chars_per_line($width);
            return self::split_html_text($html, $chars);
        }
        return ['', $html];
    }

    /**
     * Split a list or table between items/rows.
     *
     * @param string $tag
     * @param string $html
     * @param float $height
     * @param float $width
     * @param string $child li or tr
     * @return string[]
     */
    protected static function split_children(string $tag, string $html, float $height, float $width, string $child): array {
        if (!preg_match('#^(.*?<' . $tag . '\b[^>]*>)(.*)(</' . $tag . '>)\s*$#su', $html, $m)) {
            return ['', $html];
        }
        [$open, $inner, $close] = [$m[1], $m[2], $m[3]];
        // Keep table sections (thead/tbody) simple: work on the rows only.
        $inner = preg_replace('#</?(thead|tbody|tfoot)\b[^>]*>#i', '', $inner);
        preg_match_all('#<' . $child . '\b.*?</' . $child . '>#su', $inner, $items);
        $items = $items[0];
        $taken = [];
        foreach ($items as $i => $item) {
            $candidate = $open . implode('', array_merge($taken, [$item])) . $close;
            if (self::estimate($tag, $candidate, $width) > $height) {
                break;
            }
            $taken[] = $item;
        }
        if (!$taken || count($taken) === count($items)) {
            return $taken ? [$html, ''] : ['', $html];
        }
        $rest = array_slice($items, count($taken));
        if ($tag === 'table' && strpos($items[0], '<th') !== false) {
            // Repeat the header row on the next page.
            array_unshift($rest, $items[0]);
        }
        return [$open . implode('', $taken) . $close, $open . implode('', $rest) . $close];
    }

    /**
     * Split html after about $chars characters of text at a space, closing and reopening inline tags.
     *
     * @param string $html
     * @param int $chars
     * @return string[]
     */
    public static function split_html_text(string $html, int $chars): array {
        if ($chars < 20) {
            return ['', $html];
        }
        $tokens = preg_split('/(<[^>]+>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $count = 0;
        $stack = [];
        $first = '';
        foreach ($tokens as $i => $token) {
            if ($token[0] === '<') {
                if (preg_match('#^</\s*([a-z0-9]+)#i', $token, $m)) {
                    array_pop($stack);
                } else if (
                    preg_match('#^<\s*([a-z0-9]+)#i', $token, $m) && substr($token, -2) !== '/>'
                        && !in_array(strtolower($m[1]), ['br', 'img', 'hr', 'wbr'])
                ) {
                    $stack[] = [strtolower($m[1]), $token];
                }
                $first .= $token;
                continue;
            }
            $decoded = html_entity_decode($token, ENT_QUOTES, 'UTF-8');
            $len = \core_text::strlen($decoded);
            if ($count + $len < $chars) {
                $first .= $token;
                $count += $len;
                continue;
            }
            // Split this text token at the last space before the limit.
            $cut = \core_text::substr($decoded, 0, $chars - $count);
            $pos = \core_text::strrpos($cut, ' ');
            if ($pos === false || $pos < 1) {
                return ['', $html];
            }
            $first .= s(\core_text::substr($decoded, 0, $pos));
            $rest = s(\core_text::substr($decoded, $pos + 1));
            $close = implode('', array_map(fn($t) => '</' . $t[0] . '>', array_reverse($stack)));
            $reopen = implode('', array_map(fn($t) => $t[1], $stack));
            return [$first . $close, $reopen . $rest . implode('', array_slice($tokens, $i + 1))];
        }
        return [$html, ''];
    }
}
