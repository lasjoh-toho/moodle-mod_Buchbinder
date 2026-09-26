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
 * Layout system for composed pages, based on the Quarto/Pandoc Markdown format.
 *
 * Supported (a subset of Quarto that needs no external tool):
 *  - Markdown (Markdown Extra: tables, footnotes, definition lists)
 *  - fenced divs "::: {.class key=value}" … ":::" (nestable):
 *      .columns / .column width="40%"      multi column layouts
 *      .callout-note|tip|warning|important|caution title="…"   callout boxes
 *      .lines n=6                           writing lines for worksheets (Buchbinder extension)
 *      .box                                 framed box (Buchbinder extension)
 *  - images with attributes "![Caption](image.png){width=50%}", an image alone in a
 *    paragraph becomes a figure with caption
 *  - page breaks "{{< pagebreak >}}"
 *
 * Images named like clips ("ausschnitt-3.png") refer to regions cut out of imported
 * pages, so imported content can be arranged in new layouts.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class layout_renderer {
    /** @var string[] Callout types of Quarto. */
    const CALLOUTS = ['note', 'tip', 'warning', 'important', 'caution'];

    /** @var int[] Column width classes. */
    const WIDTHS = [20, 25, 30, 33, 40, 50, 60, 67, 70, 75, 80];

    /** @var string[] Available starter templates. */
    const TEMPLATES = ['blank', 'worksheet', 'twocolumns', 'imagetext', 'vocabulary'];

    /** @var callable filename => url|null */
    protected $resolveimage;

    /** @var bool render for TCPDF (tables instead of flexbox) */
    protected $print;

    /**
     * Constructor.
     *
     * @param callable $resolveimage maps an image reference to a URL (or local path when printing), null if unknown
     * @param bool $print
     */
    public function __construct(callable $resolveimage, bool $print = false) {
        $this->resolveimage = $resolveimage;
        $this->print = $print;
    }

    /**
     * Split a source at page breaks.
     *
     * @param string $source
     * @return string[] at least one element
     */
    public static function split_pages(string $source): array {
        $pages = [''];
        $infence = false;
        foreach (preg_split('/\R/', $source) as $line) {
            if (preg_match('/^\s*(\x60{3}|~{3})/', $line)) {
                $infence = !$infence;
            }
            if (!$infence && preg_match('/^\s*(\{\{<\s*pagebreak\s*>\}\}|\\\\newpage)\s*$/', $line)) {
                $pages[] = '';
                continue;
            }
            $pages[count($pages) - 1] .= $line . "\n";
        }
        $pages = array_values(array_filter(array_map('trim', $pages), fn($p) => $p !== ''));
        return $pages ?: [''];
    }

    /**
     * Parse fenced div attributes: ".a .b #id key=value key="value"".
     *
     * @param string $attrs
     * @return array ['classes' => string[], 'id' => string, 'kv' => array]
     */
    public static function parse_attributes(string $attrs): array {
        $result = ['classes' => [], 'id' => '', 'kv' => []];
        preg_match_all(
            '/\.([\w-]+)|#([\w-]+)|([\w-]+)=(?:"([^"]*)"|\'([^\']*)\'|([^\s"\']+))/u',
            $attrs,
            $matches,
            PREG_SET_ORDER
        );
        foreach ($matches as $m) {
            if (($m[1] ?? '') !== '') {
                $result['classes'][] = $m[1];
            } else if (($m[2] ?? '') !== '') {
                $result['id'] = $m[2];
            } else if (($m[3] ?? '') !== '') {
                $result['kv'][strtolower($m[3])] = ($m[4] ?? '') . ($m[5] ?? '') . ($m[6] ?? '');
            }
        }
        return $result;
    }

    /**
     * Build the block tree of fenced divs.
     *
     * @param string $source
     * @return array list of nodes: ['text' => string] or ['div' => attrs, 'children' => nodes]
     */
    public static function parse(string $source): array {
        $root = ['children' => []];
        $stack = [&$root];
        $buffer = '';
        $infence = false;
        $flush = function () use (&$buffer, &$stack) {
            if (trim($buffer) !== '') {
                $stack[count($stack) - 1]['children'][] = ['text' => $buffer];
            }
            $buffer = '';
        };
        foreach (preg_split('/\R/', $source) as $line) {
            if (preg_match('/^\s*(\x60{3}|~{3})/', $line)) {
                $infence = !$infence;
            }
            if (!$infence && preg_match('/^\s*:{3,}\s*(?:\{(.*)\}|([\w-]+))\s*$/u', $line, $m)) {
                $flush();
                $attrs = ($m[1] ?? '') !== '' ? self::parse_attributes($m[1]) :
                    ['classes' => [$m[2]], 'id' => '', 'kv' => []];
                $stack[count($stack) - 1]['children'][] = ['div' => $attrs, 'children' => []];
                $parent = &$stack[count($stack) - 1]['children'];
                $stack[] = &$parent[count($parent) - 1];
                unset($parent);
                continue;
            }
            if (!$infence && preg_match('/^\s*:{3,}\s*$/', $line)) {
                $flush();
                if (count($stack) > 1) {
                    array_pop($stack);
                }
                continue;
            }
            $buffer .= $line . "\n";
        }
        $flush();
        return $root['children'];
    }

    /**
     * Render a layout source to html.
     *
     * @param string $source
     * @return string
     */
    public function render(string $source): string {
        return $this->render_nodes(self::parse($source));
    }

    /**
     * Render nodes.
     *
     * @param array $nodes
     * @return string
     */
    protected function render_nodes(array $nodes): string {
        $html = '';
        foreach ($nodes as $node) {
            $html .= isset($node['text']) ? $this->render_markdown($node['text']) : $this->render_div($node);
        }
        return $html;
    }

    /**
     * Markdown with Quarto image attributes.
     *
     * @param string $text
     * @return string
     */
    protected function render_markdown(string $text): string {
        // Images are inserted after purification (as placeholders before), because the
        // purifier would drop figures and widths. Their attributes are validated in image().
        $images = [];
        $token = function (string $html) use (&$images): string {
            $images[] = $html;
            return 'BBLAYOUTIMG' . (count($images) - 1) . 'X';
        };
        $pattern = '!\[([^\]]*)\]\(([^)\s]+)(?:\s+"[^"]*")?\)(\{[^}]*\})?';
        // An image alone on a line becomes a figure with caption.
        $text = preg_replace_callback(
            '/^[ \t]*' . $pattern . '[ \t]*$/mu',
            fn($m) => "\n" . $token($this->image($m[2], $m[1], $m[3] ?? '', true)) . "\n",
            $text
        );
        $text = preg_replace_callback(
            '/' . $pattern . '/u',
            fn($m) => $token($this->image($m[2], $m[1], $m[3] ?? '', false)),
            $text
        );
        $html = purify_html(markdown_to_html($text));
        foreach ($images as $i => $img) {
            $html = str_replace(['<p>BBLAYOUTIMG' . $i . 'X</p>', 'BBLAYOUTIMG' . $i . 'X'], $img, $html);
        }
        return $html;
    }

    /**
     * Image html.
     *
     * @param string $src
     * @param string $alt
     * @param string $attrs Quarto attribute block, e.g. {width=50%}
     * @param bool $figure
     * @return string
     */
    protected function image(string $src, string $alt, string $attrs, bool $figure): string {
        $url = ($this->resolveimage)($src);
        if ($url === null) {
            if ($this->print || !preg_match('#^https?://#i', $src)) {
                return $alt !== '' ? '<em>[' . s($alt) . ']</em>' : '';
            }
            $url = $src;
        }
        $kv = self::parse_attributes(trim($attrs, '{}'))['kv'];
        $style = '';
        if (isset($kv['width']) && preg_match('/^\d{1,4}(\.\d+)?(%|px|em|cm|mm)?$/', $kv['width'])) {
            $width = is_numeric($kv['width']) ? $kv['width'] . 'px' : $kv['width'];
            $style = $this->print ? ' width="' . s($width) . '"' : ' style="width:' . s($width) . '"';
        }
        $img = '<img src="' . s($url) . '" alt="' . s($alt) . '" class="bb-l-img"' . $style . '>';
        if ($figure && $alt !== '') {
            return '<figure class="bb-l-figure">' . $img . '<figcaption>' . s($alt) . '</figcaption></figure>';
        }
        return $img;
    }

    /**
     * Render a fenced div.
     *
     * @param array $node
     * @return string
     */
    protected function render_div(array $node): string {
        $classes = $node['div']['classes'];
        $kv = $node['div']['kv'];
        $children = $node['children'];

        if (in_array('columns', $classes)) {
            $columns = array_values(array_filter($children, fn($c) => isset($c['div']) &&
                in_array('column', $c['div']['classes'])));
            if ($this->print) {
                $html = '<table cellpadding="4" width="100%"><tr>';
                foreach ($columns as $column) {
                    $width = $this->column_width($column['div']['kv']['width'] ?? '');
                    $html .= '<td' . ($width ? ' width="' . $width . '%"' : '') . ' valign="top">' .
                        $this->render_nodes($column['children']) . '</td>';
                }
                return $html . '</tr></table>';
            }
            $html = '<div class="bb-l-columns">';
            foreach ($columns as $column) {
                $width = $this->column_width($column['div']['kv']['width'] ?? '');
                $html .= '<div class="bb-l-column ' . ($width ? 'bb-l-w-' . $width : 'bb-l-w-auto') . '">' .
                    $this->render_nodes($column['children']) . '</div>';
            }
            return $html . '</div>';
        }

        foreach ($classes as $class) {
            if (preg_match('/^callout-(' . implode('|', self::CALLOUTS) . ')$/', $class, $m)) {
                return $this->callout($m[1], $kv['title'] ?? '', $children);
            }
        }

        if (in_array('lines', $classes)) {
            $n = max(1, min(40, (int)($kv['n'] ?? 5)));
            $label = $this->render_nodes($children);
            if ($this->print) {
                $rows = str_repeat('<tr><td style="border-bottom:0.3mm solid #888888;height:9mm;">&nbsp;</td></tr>', $n);
                return $label . '<table width="100%" cellpadding="0">' . $rows . '</table>';
            }
            return '<div class="bb-l-lines">' . $label . str_repeat('<div class="bb-l-line"></div>', $n) . '</div>';
        }

        if (in_array('box', $classes)) {
            if ($this->print) {
                return '<table border="1" cellpadding="6" width="100%"><tr><td>' . $this->render_nodes($children) .
                    '</td></tr></table>';
            }
            return '<div class="bb-l-box">' . $this->render_nodes($children) . '</div>';
        }

        $safe = array_filter($classes, fn($c) => preg_match('/^[a-z][\w-]*$/i', $c));
        return '<div class="' . s(implode(' ', $safe)) . '">' . $this->render_nodes($children) . '</div>';
    }

    /**
     * Callout box. Without title attribute a leading heading becomes the title.
     *
     * @param string $type
     * @param string $title
     * @param array $children
     * @return string
     */
    protected function callout(string $type, string $title, array $children): string {
        if (
            $title === '' && isset($children[0]['text']) &&
                preg_match('/^\s*#{1,6}\s+(.+?)\s*#*\s*\R(.*)$/su', $children[0]['text'] . "\n", $m)
        ) {
            $title = $m[1];
            $children[0]['text'] = $m[2];
        }
        if ($title === '') {
            $title = get_string('callout_' . $type, 'mod_buchbinder');
        }
        $body = $this->render_nodes($children);
        if ($this->print) {
            $colors = ['note' => '#e7f1fb', 'tip' => '#e8f6ec', 'warning' => '#fff4dc', 'important' => '#fde8e8',
                'caution' => '#fff0e0'];
            return '<table cellpadding="6" width="100%"><tr><td style="background-color:' . $colors[$type] .
                ';border-left:1.5mm solid #888888;"><strong>' . s($title) . '</strong><br>' . $body . '</td></tr></table><br>';
        }
        return '<div class="bb-l-callout bb-l-callout-' . $type . '"><div class="bb-l-callout-title">' . s($title) .
            '</div><div class="bb-l-callout-body">' . $body . '</div></div>';
    }

    /**
     * Snap a column width to the available classes.
     *
     * @param string $width e.g. "40%"
     * @return int|null
     */
    protected function column_width(string $width): ?int {
        if (!preg_match('/^(\d+(?:\.\d+)?)%?$/', trim($width), $m)) {
            return null;
        }
        $value = (float)$m[1];
        $best = null;
        foreach (self::WIDTHS as $w) {
            if ($best === null || abs($w - $value) < abs($best - $value)) {
                $best = $w;
            }
        }
        return $best;
    }

    /**
     * Starter source of a template.
     *
     * @param string $template
     * @return string
     */
    public static function template_source(string $template): string {
        $template = in_array($template, self::TEMPLATES) ? $template : 'blank';
        return get_string('layouttemplate_' . $template . '_source', 'mod_buchbinder');
    }
}
