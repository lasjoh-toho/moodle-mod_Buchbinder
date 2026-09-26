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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/pdflib.php');

/**
 * Eco print studio: builds paper saving PDFs (1-up, 2-up, 4-up, booklet) with
 * optional ink saving and burned in masks.
 *
 * Font sizes of text overlays are defined relative to a page width of 1000 units,
 * the viewer uses the same reference (container query units).
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class eco_print {
    /** @var float Slot margin in mm. */
    const MARGIN = 5;

    /** @var document */
    protected $document;

    /** @var \pdf */
    protected $pdf;

    /** @var bool */
    protected $inksaver;

    /** @var bool */
    protected $withsolutions;

    /** @var array */
    protected $sources;

    /**
     * Constructor.
     *
     * @param document $document
     * @param bool $inksaver
     * @param bool $withsolutions do not burn in masks (teacher copy)
     */
    public function __construct(document $document, bool $inksaver, bool $withsolutions) {
        $this->document = $document;
        $this->inksaver = $inksaver;
        $this->withsolutions = $withsolutions;
        $this->sources = $document->get_sources();
    }

    /**
     * Build the PDF.
     *
     * @param \stdClass[] $pages pages in print order
     * @param string $layout see imposition
     * @return \pdf
     */
    public function build(array $pages, string $layout): \pdf {
        \core_php_time_limit::raise(600);
        raise_memory_limit(MEMORY_HUGE);
        $pages = array_values($pages);
        $overlays = $this->document->get_overlays(array_map(fn($p) => $p->id, $pages));

        $this->pdf = new \pdf('P', 'mm', 'A4');
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->SetMargins(0, 0, 0);
        $this->pdf->SetAutoPageBreak(false, 0);
        $this->pdf->SetTitle($this->document->get_instance()->name);
        $this->pdf->SetCreator('Moodle mod_buchbinder');

        // Keep double pages: the side of the first printed page decides the slot it goes into.
        $positions = $this->document->get_positions();
        $first = $pages ? ($positions[$pages[0]->id] ?? 1) : 1;
        $startright = $this->document->side_at($first) === booklet::RIGHT;
        foreach (imposition::sides($layout, count($pages), $startright) as $side) {
            $slots = $this->slot_boxes($layout, $side, $pages);
            foreach ($side as $i => $position) {
                if ($position === null) {
                    continue;
                }
                $page = $pages[$position - 1];
                $this->render_page($page, $overlays[$page->id] ?? [], ...$slots[$i]);
            }
        }
        return $this->pdf;
    }

    /**
     * Add a sheet side and compute the slot boxes.
     *
     * @param string $layout
     * @param array $side page positions
     * @param array $pages
     * @return array[] [x, y, w, h] per slot in mm
     */
    protected function slot_boxes(string $layout, array $side, array $pages): array {
        switch ($layout) {
            case imposition::LAYOUT_2UP:
            case imposition::LAYOUT_BOOKLET:
                $this->pdf->AddPage('L', 'A4');
                return [[0, 0, 148.5, 210], [148.5, 0, 148.5, 210]];
            case imposition::LAYOUT_4UP:
                $this->pdf->AddPage('P', 'A4');
                return [[0, 0, 105, 148.5], [105, 0, 105, 148.5], [0, 148.5, 105, 148.5], [105, 148.5, 105, 148.5]];
            default:
                $page = $pages[$side[0] - 1];
                if ($page->width > $page->height) {
                    $this->pdf->AddPage('L', 'A4');
                    return [[0, 0, 297, 210]];
                }
                $this->pdf->AddPage('P', 'A4');
                return [[0, 0, 210, 297]];
        }
    }

    /**
     * Render one page into a slot.
     *
     * @param \stdClass $page
     * @param \stdClass[] $overlays
     * @param float $sx
     * @param float $sy
     * @param float $sw
     * @param float $sh
     */
    protected function render_page(\stdClass $page, array $overlays, float $sx, float $sy, float $sw, float $sh): void {
        $citation = $this->citation_text($page);
        $footer = $citation !== '' ? 4 : 0;
        $aw = $sw - 2 * self::MARGIN;
        $ah = $sh - 2 * self::MARGIN - $footer;
        $ratio = ($page->width && $page->height) ? $page->width / $page->height : 210 / 297;
        if ($aw / $ah > $ratio) {
            $bh = $ah;
            $bw = $ah * $ratio;
        } else {
            $bw = $aw;
            $bh = $aw / $ratio;
        }
        $bx = $sx + self::MARGIN + ($aw - $bw) / 2;
        $by = $sy + self::MARGIN + ($ah - $bh) / 2;

        if ($page->pagetype === 'html' || $page->pagetype === 'layout') {
            $this->render_html($page, $bx, $by, $bw, $bh);
        } else if ($page->pagetype === 'canvas') {
            // White page: content comes from the frames below.
            $this->pdf->Rect($bx, $by, $bw, $bh, 'D', ['all' => ['width' => 0.1, 'color' => [220, 220, 220]]]);
        } else {
            $img = $this->document->load_page_image($page);
            if ($img) {
                if ($this->inksaver) {
                    $img = image_cleanup::ink_saver($img);
                }
                ob_start();
                imagejpeg($img, null, 82);
                $this->pdf->Image('@' . ob_get_clean(), $bx, $by, $bw, $bh, 'JPEG');
            }
        }

        // Frames are the content layer, interactive layers lie on top.
        usort($overlays, fn($a, $b) => (int)!overlay_types::is_frame($a->overlaytype) <=>
            (int)!overlay_types::is_frame($b->overlaytype));
        foreach ($overlays as $overlay) {
            if (overlay_types::is_frame($overlay->overlaytype)) {
                $this->render_frame(
                    $overlay,
                    $bx + $overlay->x * $bw,
                    $by + $overlay->y * $bh,
                    $overlay->w * $bw,
                    $overlay->h * $bh,
                    $bw
                );
                continue;
            }
            $ox = $bx + $overlay->x * $bw;
            $oy = $by + $overlay->y * $bh;
            $ow = $overlay->w * $bw;
            $oh = $overlay->h * $bh;
            $s = $overlay->settings;
            if ($overlay->overlaytype === overlay_types::MASK && !$this->withsolutions) {
                $color = $s['style'] === 'black' && !$this->inksaver ? [0, 0, 0] : [255, 255, 255];
                $this->pdf->Rect($ox, $oy, $ow, $oh, 'F', [], $color);
                if ($this->inksaver || $s['style'] !== 'black') {
                    $this->pdf->Rect($ox, $oy, $ow, $oh, 'D', ['all' => ['width' => 0.2, 'color' => [160, 160, 160]]]);
                }
            } else if ($overlay->overlaytype === overlay_types::TEXTBOX) {
                $bg = $this->inksaver ? [255, 255, 255] : self::rgb($s['bgcolor']);
                $this->pdf->Rect($ox, $oy, $ow, $oh, 'F', [], $bg);
                $this->pdf->SetTextColorArray($this->inksaver ? [0, 0, 0] : self::rgb($s['color']));
                $pt = $s['fontsize'] * $bw / 1000 * 72 / 25.4;
                $this->pdf->SetFont('freesans', $s['bold'] ? 'B' : '', $pt);
                $align = ['left' => 'L', 'center' => 'C', 'right' => 'R'][$s['align']] ?? 'L';
                $this->pdf->MultiCell($ow, $oh, $s['text'], 0, $align, false, 0, $ox, $oy, true, 0, false, true, $oh, 'T', true);
            }
        }

        if ($citation !== '') {
            $this->pdf->SetTextColor(90, 90, 90);
            $this->pdf->SetFont('freesans', '', 6);
            $this->pdf->MultiCell(
                $aw,
                $footer,
                $citation,
                0,
                'L',
                false,
                0,
                $sx + self::MARGIN,
                $sy + $sh - self::MARGIN - $footer,
                true,
                0,
                false,
                true,
                $footer,
                'M',
                true
            );
        }
    }

    /**
     * Render a text or image frame.
     *
     * @param \stdClass $frame
     * @param float $x
     * @param float $y
     * @param float $w
     * @param float $h
     * @param float $pagewidth width of the page in mm (for font scaling)
     */
    protected function render_frame(\stdClass $frame, float $x, float $y, float $w, float $h, float $pagewidth): void {
        $s = $frame->settings;
        if ($frame->overlaytype === overlay_types::TEXTFRAME) {
            if (!empty($s['bgcolor']) && !$this->inksaver) {
                $this->pdf->Rect($x, $y, $w, $h, 'F', [], self::rgb($s['bgcolor']));
            }
            if (!empty($s['border'])) {
                $this->pdf->Rect($x, $y, $w, $h, 'D', ['all' => ['width' => 0.3, 'color' => [60, 60, 60]]]);
            }
            $this->pdf->SetTextColor(0, 0, 0);
            // 11 pt body text on a 210 mm page, scaled to the printed page size.
            // The Tufte style is set in a serif typeface with italic headings.
            $serif = in_array($s['style'] ?? '', ['tufte', 'sidenote'], true);
            $size = max(4, 11 * $pagewidth / booklet::PAGE_WIDTH_MM * ($s['fontscale'] ?? 1));
            $this->pdf->SetFont($serif ? 'freeserif' : 'freesans', '', $size);
            $this->pdf->setHtmlVSpace(['p' => [['h' => 0, 'n' => 0], ['h' => 1, 'n' => 0.4]]]);
            $html = format_text($s['html'] ?? '', FORMAT_HTML, ['context' => $this->document->get_context(), 'filter' => false]);
            if ($serif) {
                $html = preg_replace('#<(h[1-6])\b([^>]*)>(.*?)</\1>#si', '<$1$2><i style="font-weight:normal">$3</i></$1>', $html);
            }
            // TCPDF ignores the CSS of the viewer: give tables visible cell borders.
            $html = preg_replace('/<table\b/i', '<table border="1" cellpadding="3"', $html);
            $this->pdf->writeHTMLCell($w, $h, $x, $y, $html, 0, 0, false, true, '', true);
            return;
        }
        $file = $this->document->frame_image_file($frame);
        if (!$file) {
            return;
        }
        $img = @imagecreatefromstring($file->get_content());
        if (!$img) {
            return;
        }
        if ($this->inksaver) {
            $img = image_cleanup::ink_saver($img);
        }
        $iw = imagesx($img);
        $ih = imagesy($img);
        $boxratio = $w / max(0.001, $h);
        if (($s['fit'] ?? 'contain') === 'cover') {
            // Crop the image to the frame.
            if ($iw / $ih > $boxratio) {
                $cw = (int)round($ih * $boxratio);
                $img = imagecrop($img, ['x' => (int)(($iw - $cw) / 2), 'y' => 0, 'width' => $cw, 'height' => $ih]) ?: $img;
            } else {
                $ch = (int)round($iw / $boxratio);
                $img = imagecrop($img, ['x' => 0, 'y' => (int)(($ih - $ch) / 2), 'width' => $iw, 'height' => $ch]) ?: $img;
            }
            $dw = $w;
            $dh = $h;
        } else if ($iw / $ih > $boxratio) {
            $dw = $w;
            $dh = $w * $ih / $iw;
        } else {
            $dh = $h;
            $dw = $h * $iw / $ih;
        }
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $this->pdf->Image('@' . image_cleanup::to_png($img), $x + ($w - $dw) / 2, $y + ($h - $dh) / 2, $dw, $dh, 'PNG');
    }

    /**
     * Render an html page. Embedded images are resolved to local temp files.
     *
     * @param \stdClass $page
     * @param float $x
     * @param float $y
     * @param float $w
     * @param float $h
     */
    protected function render_html(\stdClass $page, float $x, float $y, float $w, float $h): void {
        $context = $this->document->get_context();
        // TCPDF gets embedded images as data ("@" + base64), which works independent of paths and URLs.
        $embed = fn(\stored_file $file) => '@' . base64_encode($file->get_content());
        if ($page->pagetype === 'layout') {
            // Composed page: tables instead of flexbox. Text parts are purified by the renderer.
            $clips = $this->document->get_clips();
            $renderer = new layout_renderer(fn(string $src) => isset($clips[$src]) ? $embed($clips[$src]) : null, true);
            $html = $renderer->render($page->content ?? '');
        } else {
            $html = format_text($page->content ?? '', FORMAT_HTML, ['context' => $context, 'filter' => false]);
            $fs = get_file_storage();
            foreach ($fs->get_area_files($context->id, 'mod_buchbinder', 'pagecontent', $page->id, 'id', false) as $file) {
                $data = $embed($file);
                $prefix = '@@PLUGINFILE@@' . $file->get_filepath();
                $html = str_replace([$prefix . rawurlencode($file->get_filename()), $prefix . $file->get_filename()], $data, $html);
            }
        }
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont('freesans', '', max(6, 11 * $w / 190));
        $this->pdf->setHtmlVSpace(['p' => [['h' => 0, 'n' => 0], ['h' => 1, 'n' => 0.5]]]);
        $this->pdf->writeHTMLCell($w, $h, $x, $y, $html, 0, 0, false, true, '', true);
    }

    /**
     * Plain text citation of the page's source.
     *
     * @param \stdClass $page
     * @return string
     */
    protected function citation_text(\stdClass $page): string {
        if (!$page->sourceid || empty($this->sources[$page->sourceid])) {
            return '';
        }
        $source = $this->sources[$page->sourceid];
        if (!document::has_citation($source)) {
            return '';
        }
        $parts = array_filter([$source->author, $source->title, $source->url]);
        if ($source->url && $source->timeaccessed) {
            $parts[] = get_string(
                'citationaccessed',
                'mod_buchbinder',
                userdate($source->timeaccessed, get_string('strftimedate', 'langconfig'))
            );
        }
        return get_string('source', 'mod_buchbinder') . ': ' . implode(', ', $parts);
    }

    /**
     * Hex colour to rgb.
     *
     * @param string $hex
     * @return int[]
     */
    protected static function rgb(string $hex): array {
        $hex = ltrim($hex, '#');
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
