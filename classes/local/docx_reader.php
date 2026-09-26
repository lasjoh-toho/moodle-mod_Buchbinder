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
 * Minimal Word (.docx) to HTML conversion.
 *
 * Used when no document converter (e.g. fileconverter_unoconv) is available.
 * Keeps headings, paragraphs, bold/italic runs, lists, tables and manual page breaks.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class docx_reader {
    /** @var string WordprocessingML namespace. */
    const NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** @var string DrawingML namespace. */
    const NS_A = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    /** @var string Relationships namespace. */
    const NS_R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /** @var array relationship id => target of the document being read */
    protected static $rels = [];

    /** @var int[] footnote id => display number, in the order of the references */
    protected static $footnoterefs = [];

    /**
     * Convert a docx file into html pages (split at manual page breaks).
     *
     * @param string $path
     * @return string[]
     */
    public static function to_html_pages(string $path): array {
        return self::read($path)['pages'];
    }

    /**
     * Convert a docx file into one html document with page breaks and embedded images.
     *
     * Images are referenced as src="docx:word/media/…" and returned in 'media'.
     *
     * @param string $path
     * @return array ['html' => string, 'media' => [src => ['data' => string, 'filename' => string]]]
     */
    public static function to_html(string $path): array {
        $result = self::read($path);
        return ['html' => implode("\n<hr class=\"bb-pagebreak\">\n", $result['pages']), 'media' => $result['media']];
    }

    /**
     * Read the document.
     *
     * @param string $path
     * @return array ['pages' => string[], 'media' => array]
     */
    protected static function read(string $path): array {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \moodle_exception('errorimportformat', 'mod_buchbinder');
        }
        $xml = $zip->getFromName('word/document.xml');
        $footnotesxml = $zip->getFromName('word/footnotes.xml');
        self::$rels = [];
        self::$footnoterefs = [];
        $media = [];
        $relsxml = $zip->getFromName('word/_rels/document.xml.rels');
        if ($relsxml !== false) {
            $rels = new \DOMDocument();
            $rels->loadXML($relsxml, LIBXML_NONET);
            foreach ($rels->getElementsByTagName('Relationship') as $rel) {
                $target = $rel->getAttribute('Target');
                if (strpos($target, 'media/') === 0) {
                    $name = 'word/' . $target;
                    $data = $zip->getFromName($name);
                    if ($data !== false) {
                        self::$rels[$rel->getAttribute('Id')] = 'docx:' . $name;
                        $media['docx:' . $name] = ['data' => $data, 'filename' => basename($name)];
                    }
                }
            }
        }
        $zip->close();
        if ($xml === false) {
            throw new \moodle_exception('errorimportformat', 'mod_buchbinder');
        }
        $dom = new \DOMDocument();
        $dom->loadXML($xml, LIBXML_NONET);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', self::NS);
        $xpath->registerNamespace('a', self::NS_A);
        $xpath->registerNamespace('r', self::NS_R);
        $body = $xpath->query('/w:document/w:body')->item(0);
        if (!$body) {
            return ['pages' => [], 'media' => $media];
        }
        $pages = [];
        $html = '';
        $inlist = false;
        foreach ($body->childNodes as $node) {
            if ($node->namespaceURI !== self::NS) {
                continue;
            }
            if ($node->localName === 'tbl') {
                if ($inlist) {
                    $html .= '</ul>';
                    $inlist = false;
                }
                $html .= self::table($xpath, $node);
                continue;
            }
            if ($node->localName !== 'p') {
                continue;
            }
            $text = self::runs($xpath, $node);
            $style = (string)$xpath->evaluate('string(w:pPr/w:pStyle/@w:val)', $node);
            $islist = $xpath->query('w:pPr/w:numPr', $node)->length > 0;
            if ($islist && !$inlist) {
                $html .= '<ul>';
                $inlist = true;
            } else if (!$islist && $inlist) {
                $html .= '</ul>';
                $inlist = false;
            }
            if ($islist) {
                $html .= '<li>' . $text . '</li>';
            } else if (preg_match('/(?:Heading|berschrift|Title)\s*(\d?)/i', $style, $m)) {
                $level = min(4, max(1, (int)($m[1] ?: 1)) + 1);
                $html .= "<h{$level}>{$text}</h{$level}>";
            } else if ($text !== '') {
                $html .= '<p>' . $text . '</p>';
            }
            if ($xpath->query('.//w:br[@w:type="page"]', $node)->length > 0) {
                if ($inlist) {
                    $html .= '</ul>';
                    $inlist = false;
                }
                $pages[] = $html;
                $html = '';
            }
        }
        if ($inlist) {
            $html .= '</ul>';
        }
        if (self::$footnoterefs && $footnotesxml !== false) {
            // Footnotes in the format of Markdown Extra: a list at the end, see blocks::extract_notes().
            $html .= self::footnotes($footnotesxml);
        }
        if (trim($html) !== '') {
            $pages[] = $html;
        }
        return ['pages' => $pages, 'media' => $media];
    }

    /**
     * Html list of the referenced footnotes.
     *
     * @param string $xml content of word/footnotes.xml
     * @return string
     */
    protected static function footnotes(string $xml): string {
        $dom = new \DOMDocument();
        if (!@$dom->loadXML($xml, LIBXML_NONET)) {
            return '';
        }
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', self::NS);
        $xpath->registerNamespace('a', self::NS_A);
        $xpath->registerNamespace('r', self::NS_R);
        $notes = [];
        foreach ($xpath->query('/w:footnotes/w:footnote') as $note) {
            $id = $note->getAttributeNS(self::NS, 'id');
            if (!isset(self::$footnoterefs[$id])) {
                continue;
            }
            $paragraphs = [];
            foreach ($xpath->query('w:p', $note) as $p) {
                $text = trim(self::runs($xpath, $p));
                if ($text !== '') {
                    $paragraphs[] = '<p>' . $text . '</p>';
                }
            }
            $notes[self::$footnoterefs[$id]] = '<li id="fn:' . self::$footnoterefs[$id] . '">' . implode('', $paragraphs) . '</li>';
        }
        if (!$notes) {
            return '';
        }
        ksort($notes);
        return '<div class="footnotes"><hr><ol>' . implode('', $notes) . '</ol></div>';
    }

    /**
     * Html of the runs of a paragraph.
     *
     * @param \DOMXPath $xpath
     * @param \DOMNode $p
     * @return string
     */
    protected static function runs(\DOMXPath $xpath, \DOMNode $p): string {
        $out = '';
        foreach ($xpath->query('.//w:r', $p) as $run) {
            $text = '';
            foreach ($xpath->query('.//a:blip/@r:embed', $run) as $embed) {
                if (isset(self::$rels[$embed->value])) {
                    $out .= '<img src="' . s(self::$rels[$embed->value]) . '" alt="">';
                }
            }
            foreach ($run->childNodes as $child) {
                if ($child->localName === 't') {
                    $text .= s($child->textContent);
                } else if ($child->localName === 'tab') {
                    $text .= ' ';
                } else if ($child->localName === 'br' && $child->getAttributeNS(self::NS, 'type') !== 'page') {
                    $text .= '<br>';
                } else if ($child->localName === 'footnoteReference') {
                    $id = $child->getAttributeNS(self::NS, 'id');
                    if (!isset(self::$footnoterefs[$id])) {
                        self::$footnoterefs[$id] = count(self::$footnoterefs) + 1;
                    }
                    $number = self::$footnoterefs[$id];
                    $text .= '<sup><a href="#fn:' . $number . '" class="footnote-ref">' . $number . '</a></sup>';
                }
            }
            if ($text === '') {
                continue;
            }
            if ($xpath->query('w:rPr/w:b[not(@w:val="0")]', $run)->length) {
                $text = '<strong>' . $text . '</strong>';
            }
            if ($xpath->query('w:rPr/w:i[not(@w:val="0")]', $run)->length) {
                $text = '<em>' . $text . '</em>';
            }
            $out .= $text;
        }
        return $out;
    }

    /**
     * Html of a table.
     *
     * @param \DOMXPath $xpath
     * @param \DOMNode $tbl
     * @return string
     */
    protected static function table(\DOMXPath $xpath, \DOMNode $tbl): string {
        $html = '<table class="table table-bordered">';
        foreach ($xpath->query('w:tr', $tbl) as $tr) {
            $html .= '<tr>';
            foreach ($xpath->query('w:tc', $tr) as $tc) {
                $cell = [];
                foreach ($xpath->query('w:p', $tc) as $p) {
                    $cell[] = self::runs($xpath, $p);
                }
                $html .= '<td>' . implode('<br>', $cell) . '</td>';
            }
            $html .= '</tr>';
        }
        return $html . '</table>';
    }
}
