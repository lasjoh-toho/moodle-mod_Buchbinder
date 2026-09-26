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
 * Multi format intake: PDF, Word, HTML, images, TIFF, comic archives (cbz).
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class importer {
    /** @var string[] Accepted file extensions. */
    const ACCEPTED = ['.pdf', '.docx', '.html', '.htm', '.md', '.markdown', '.png', '.jpg', '.jpeg', '.gif', '.webp',
        '.tif', '.tiff', '.cbz', '.zip'];

    /** @var document */
    protected $document;

    /** @var array options: chop, deskew, shadow, split (scans), startright (documents start on a right page) */
    protected $ops;

    /** @var int */
    protected $maxpages;

    /** @var int */
    protected $dpi;

    /**
     * Constructor.
     *
     * @param document $document
     * @param array $ops cleanup steps applied to raster pages
     */
    public function __construct(document $document, array $ops = []) {
        $this->document = $document;
        $this->ops = $ops;
        $this->maxpages = max(1, (int)(get_config('buchbinder', 'maxpages') ?: 300));
        $this->dpi = max(72, (int)(get_config('buchbinder', 'rasterdpi') ?: 150));
    }

    /**
     * Import an uploaded file.
     *
     * @param \stored_file $file
     * @param array $meta source metadata (title, author, url)
     * @return int number of created pages
     */
    public function import_file(\stored_file $file, array $meta = []): int {
        \core_php_time_limit::raise(600);
        raise_memory_limit(MEMORY_HUGE);

        $filename = $file->get_filename();
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $type = match ($ext) {
            'pdf' => 'pdf',
            'docx' => 'docx',
            'html', 'htm' => 'html',
            'md', 'markdown' => 'md',
            'cbz', 'zip' => 'cbz',
            'tif', 'tiff' => 'tiff',
            'png', 'jpg', 'jpeg', 'gif', 'webp' => 'image',
            default => throw new \moodle_exception('errorimportformat', 'mod_buchbinder'),
        };

        $meta['filename'] = $filename;
        if (empty($meta['title'])) {
            $meta['title'] = pathinfo($filename, PATHINFO_FILENAME);
        }
        $sourceid = $this->document->add_source($type === 'tiff' ? 'image' : $type, $meta);

        // Keep the original for provenance and re-processing.
        get_file_storage()->create_file_from_storedfile([
            'contextid' => $this->document->get_context()->id,
            'component' => 'mod_buchbinder',
            'filearea' => 'source',
            'itemid' => $sourceid,
            'filepath' => '/',
        ], $file);

        switch ($type) {
            case 'pdf':
                return $this->import_pdf($file, $sourceid);
            case 'docx':
                return $this->import_docx($file, $sourceid);
            case 'html':
                $html = self::html_body($file->get_content());
                return $this->flow(blocks::from_html($html, $this->web_image_resolver($meta['url'] ?? '')), $sourceid);
            case 'md':
                return $this->flow(blocks::from_markdown(
                    $file->get_content(),
                    $this->web_image_resolver($meta['url'] ?? '')
                ), $sourceid);
            case 'cbz':
                return $this->import_archive($file, $sourceid);
            case 'tiff':
                return $this->import_tiff($file, $sourceid);
            default:
                return $this->add_scan(image_cleanup::load($file->get_content()), $sourceid);
        }
    }

    /**
     * Add a raster page with scan optimisation and double page handling.
     *
     * @param \GdImage $img
     * @param int $sourceid
     * @return int number of pages created
     */
    public function add_scan(\GdImage $img, int $sourceid): int {
        $img = document::apply_cleanup($img, $this->ops);
        $landscape = imagesx($img) > imagesy($img);
        if ($landscape && in_array('split', $this->ops)) {
            $gutter = image_cleanup::find_gutter($img);
            if ($gutter !== null) {
                [$left, $right] = image_cleanup::split($img, $gutter);
                // The left half must lie on a left page of the booklet: add a blank page if necessary.
                $this->document->pad_to_side(booklet::LEFT);
                $page = $this->document->add_image_page($left, $sourceid, ['spreadside' => 'left']);
                global $DB;
                $DB->set_field('buchbinder_page', 'spreadid', $page->id, ['id' => $page->id]);
                $this->document->add_image_page($right, $sourceid, ['spreadid' => $page->id, 'spreadside' => 'right']);
                return 2;
            }
        }
        // Wide figures, maps and tables stay protected landscape pages.
        $this->document->add_image_page($img, $sourceid, ['landscapelock' => (int)$landscape]);
        return 1;
    }

    /**
     * Rasterise a PDF with Ghostscript.
     *
     * @param \stored_file $file
     * @param int $sourceid
     * @return int
     */
    protected function import_pdf(\stored_file $file, int $sourceid): int {
        global $CFG;
        if (empty($CFG->pathtogs) || !file_is_executable($CFG->pathtogs)) {
            throw new \moodle_exception('errornoghostscript', 'mod_buchbinder');
        }
        $dir = make_request_directory();
        $pdf = $file->copy_content_to_temp();
        $cmd = escapeshellarg($CFG->pathtogs) . ' -q -dSAFER -dBATCH -dNOPAUSE -sDEVICE=png16m'
            . ' -dTextAlphaBits=4 -dGraphicsAlphaBits=4'
            . ' -r' . (int)$this->dpi
            . ' -dFirstPage=1 -dLastPage=' . (int)$this->maxpages
            . ' -sOutputFile=' . escapeshellarg($dir . '/page-%05d.png')
            . ' ' . escapeshellarg($pdf) . ' 2>&1';
        exec($cmd, $output, $status);
        @unlink($pdf);
        $files = glob($dir . '/page-*.png');
        sort($files);
        if ($status !== 0 && !$files) {
            throw new \moodle_exception('errorpdfconversion', 'mod_buchbinder', '', s(implode(' ', $output)));
        }
        $count = 0;
        foreach ($files as $png) {
            $count += $this->add_scan(image_cleanup::load(file_get_contents($png)), $sourceid);
            @unlink($png);
        }
        return $count;
    }

    /**
     * Word import: text, tables and images become frames on booklet pages.
     *
     * @param \stored_file $file
     * @param int $sourceid
     * @return int
     */
    protected function import_docx(\stored_file $file, int $sourceid): int {
        $path = $file->copy_content_to_temp();
        try {
            $doc = docx_reader::to_html($path);
        } finally {
            @unlink($path);
        }
        $media = $doc['media'];
        return $this->flow(blocks::from_html($doc['html'], fn($src) => $media[$src] ?? null), $sourceid);
    }

    /**
     * Place blocks as frames on new pages.
     *
     * @param array $blocks
     * @param int|null $sourceid
     * @return int number of pages created
     */
    public function flow(array $blocks, ?int $sourceid): int {
        $flow = new flow($this->document, $sourceid);
        return count($flow->run($blocks, in_array('startright', $this->ops)));
    }

    /**
     * Resolver for images of imported html/Markdown: data URIs and, with the harvester enabled,
     * absolute URLs (relative to the source URL).
     *
     * @param string $baseurl
     * @return callable
     */
    public function web_image_resolver(string $baseurl): callable {
        $maxbytes = document::max_bytes($this->document->get_context());
        return function (string $src) use ($baseurl, $maxbytes) {
            if (preg_match('#^data:image/(png|jpe?g|gif|webp);base64,(.+)$#is', $src, $m)) {
                $data = base64_decode($m[2], true);
                return $data === false ? null : ['data' => $data, 'filename' => 'image.' . strtolower($m[1])];
            }
            if (!harvester::is_enabled() || !get_config('buchbinder', 'harvestimages')) {
                return null;
            }
            $url = $baseurl !== '' ? harvester::absolute_url($baseurl, $src) : (preg_match('#^https?://#i', $src) ? $src : null);
            if (!$url) {
                return null;
            }
            try {
                $data = harvester::fetch($url, $maxbytes);
            } catch (\moodle_exception $e) {
                return null;
            }
            return ['data' => $data, 'filename' => basename(parse_url($url, PHP_URL_PATH) ?: 'image')];
        };
    }

    /**
     * Comic archive / zip of images in natural order.
     *
     * @param \stored_file $file
     * @param int $sourceid
     * @return int
     */
    protected function import_archive(\stored_file $file, int $sourceid): int {
        $dir = make_request_directory();
        $packer = get_file_packer('application/zip');
        $packer->extract_to_pathname($file, $dir);
        $images = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (preg_match('/\.(png|jpe?g|gif|webp)$/i', $f->getFilename()) && strpos($f->getPathname(), '__MACOSX') === false) {
                $images[] = $f->getPathname();
            }
        }
        natcasesort($images);
        $count = 0;
        foreach (array_slice($images, 0, $this->maxpages) as $path) {
            // Comic pages are layouted art: no splitting, but cleanup is fine.
            $img = image_cleanup::load(file_get_contents($path));
            $img = document::apply_cleanup($img, array_diff($this->ops, ['split', 'deskew']));
            $this->document->add_image_page($img, $sourceid, ['landscapelock' => (int)(imagesx($img) > imagesy($img))]);
            $count++;
        }
        return $count;
    }

    /**
     * Multi page TIFF (requires the imagick extension).
     *
     * @param \stored_file $file
     * @param int $sourceid
     * @return int
     */
    protected function import_tiff(\stored_file $file, int $sourceid): int {
        if (!extension_loaded('imagick')) {
            throw new \moodle_exception('errornoimagick', 'mod_buchbinder');
        }
        $im = new \Imagick();
        $im->readImageBlob($file->get_content());
        $count = 0;
        foreach ($im as $frame) {
            if ($count >= $this->maxpages) {
                break;
            }
            $frame->setImageFormat('png');
            $count += $this->add_scan(image_cleanup::load($frame->getImageBlob()), $sourceid);
        }
        return $count;
    }

    /**
     * Inner html of the body of an html document.
     *
     * @param string $html
     * @return string
     */
    public static function html_body(string $html): string {
        if (preg_match('#<body[^>]*>(.*)</body>#is', $html, $m)) {
            return $m[1];
        }
        return $html;
    }

    /**
     * Create empty worksheets.
     *
     * @param string $template
     * @param int $count
     * @param bool $landscape
     * @return int
     */
    public function add_blank_pages(string $template, int $count, bool $landscape): int {
        $template = in_array($template, blank_page::TEMPLATES) ? $template : 'blank';
        $sourceid = $this->document->add_source('blank', [
            'title' => get_string('template_' . $template, 'mod_buchbinder'),
            'showcitation' => 0,
        ]);
        $count = max(1, min(50, $count));
        for ($i = 0; $i < $count; $i++) {
            $this->document->add_image_page(
                blank_page::render($template, $landscape, $this->dpi),
                $sourceid,
                ['landscapelock' => (int)$landscape]
            );
        }
        return $count;
    }

    /**
     * Place a web or clipboard snippet as frames on new pages.
     *
     * @param string $type web or clipboard
     * @param string $html sanitised html
     * @param array $images filename => content, referenced as @@PLUGINFILE@@/filename
     * @param array $meta title, url, author
     * @param int|null $sourceid existing source record (otherwise one is created)
     * @return \stdClass first created page
     */
    public function add_snippet(string $type, string $html, array $images, array $meta, ?int $sourceid = null): \stdClass {
        $sourceid = $sourceid ?? $this->document->add_source($type, $meta);
        $resolver = function (string $src) use ($images) {
            $name = rawurldecode(preg_replace('#^@@PLUGINFILE@@/#', '', $src));
            return isset($images[$name]) ? ['data' => $images[$name], 'filename' => $name] : null;
        };
        $flow = new flow($this->document, $sourceid);
        $pages = $flow->run(blocks::from_html($html, $resolver), in_array('startright', $this->ops));
        return $pages[0] ?? $this->document->add_canvas_page(null, ['sourceid' => $sourceid]);
    }
}
