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
 * Splits imported html (from Word, Markdown, web pages or the clipboard) into content blocks
 * that the layout flow places as frames: text blocks, images and page breaks.
 *
 * Block formats:
 *  - ['type' => 'text', 'tag' => 'p|h1..h6|ul|ol|table|blockquote|pre|dl', 'html' => string]
 *  - ['type' => 'image', 'data' => string, 'filename' => string, 'alt' => string, 'caption' => string,
 *     'width' => int, 'height' => int]
 *  - ['type' => 'pagebreak']
 *
 * With notes enabled, footnotes (Markdown Extra, Pandoc, Word) and side or margin notes
 * (Tufte CSS: span.sidenote, span.marginnote; Quarto: .column-margin; aside) are taken out of the
 * text and attached to the text block that references them as 'notes' => string[] (html).
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class blocks {
    /** @var string[] Elements that become one text block. */
    const TEXT_TAGS = ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'table', 'blockquote', 'pre', 'dl'];

    /** @var string[] Containers whose children are processed. */
    const CONTAINER_TAGS = ['body', 'div', 'section', 'article', 'main', 'header', 'footer', 'aside', 'nav', 'center',
        'details', 'summary', 'form', 'fieldset'];

    /** @var callable src => ['data' => string, 'filename' => string]|null */
    protected $resolveimage;

    /** @var array */
    protected $blocks = [];

    /** @var \DOMDocument */
    protected $dom;

    /** @var bool take notes out of the text */
    protected $withnotes = false;

    /** @var array[] collected notes: ['html' => string, 'numbered' => bool] */
    protected $notes = [];

    /**
     * Split html into blocks.
     *
     * @param string $html
     * @param callable|null $resolveimage returns image data for a src or null
     * @param bool $notes take footnotes and margin notes out of the text (for page styles with a note column)
     * @return array blocks
     */
    public static function from_html(string $html, ?callable $resolveimage = null, bool $notes = false): array {
        $splitter = new self();
        $splitter->resolveimage = $resolveimage ?? fn($src) => null;
        $splitter->withnotes = $notes;
        return $splitter->split($html);
    }

    /**
     * Split Markdown (Markdown Extra) into blocks.
     *
     * @param string $markdown
     * @param callable|null $resolveimage
     * @param bool $notes take footnotes and margin notes out of the text
     * @return array
     */
    public static function from_markdown(string $markdown, ?callable $resolveimage = null, bool $notes = false): array {
        // Quarto/Pandoc page breaks and "\newpage".
        $markdown = preg_replace(
            '/^\s*(\{\{<\s*pagebreak\s*>\}\}|\\\\newpage)\s*$/m',
            "\n<hr class=\"bb-pagebreak\">\n",
            $markdown
        );
        // Quarto margin content (a ::: {.column-margin} block) becomes a margin note.
        $markdown = preg_replace_callback(
            '/^:::+\s*\{\.(column-margin|aside)\}\s*\n(.*?)\n:::+\s*$/ms',
            fn($m) => '<div class="column-margin" markdown="1">' . "\n\n" . $m[2] . "\n\n</div>",
            $markdown
        );
        return self::from_html(markdown_to_html($markdown), $resolveimage, $notes);
    }

    /**
     * Do the work.
     *
     * @param string $html
     * @return array
     */
    protected function split(string $html): array {
        $this->dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $this->dom->loadHTML(
            '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>',
            LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR
        );
        libxml_clear_errors();
        $body = $this->dom->getElementsByTagName('body')->item(0);
        foreach (
            ['script', 'style', 'noscript', 'iframe', 'object', 'embed', 'svg', 'template', 'button', 'input',
                'select', 'textarea'] as $tag
        ) {
            foreach (iterator_to_array($this->dom->getElementsByTagName($tag)) as $node) {
                $node->parentNode->removeChild($node);
            }
        }
        if ($this->withnotes) {
            $this->collect_notes();
        }
        $this->walk($body);
        return $this->withnotes ? $this->attach_notes($this->blocks) : $this->blocks;
    }

    /**
     * Replace footnote references and margin notes by markers and remember their content.
     */
    protected function collect_notes(): void {
        $xpath = new \DOMXPath($this->dom);
        $class = fn($name) => "contains(concat(' ', normalize-space(@class), ' '), ' {$name} ')";
        // Footnote definitions: li id="fn:1" (Markdown Extra, Word) or id="fn1" (Pandoc).
        $definitions = [];
        foreach (iterator_to_array($xpath->query('//li[starts-with(@id, "fn")]')) as $li) {
            foreach (
                iterator_to_array($xpath->query('.//a[starts-with(@href, "#fnref") or ' . $class('footnote-backref')
                    . ' or ' . $class('footnote-back') . ']', $li)) as $back
            ) {
                $back->parentNode->removeChild($back);
            }
            $definitions[$li->getAttribute('id')] = trim($this->inner_html($li));
        }
        foreach (iterator_to_array($xpath->query('//*[' . $class('footnotes') . ']')) as $container) {
            $container->parentNode->removeChild($container);
        }
        foreach (iterator_to_array($xpath->query('//li[starts-with(@id, "fn")]')) as $li) {
            if ($li->parentNode && $li->parentNode->parentNode) {
                $li->parentNode->parentNode->removeChild($li->parentNode);
            }
        }
        // References.
        foreach (iterator_to_array($xpath->query('//a[starts-with(@href, "#fn")]')) as $ref) {
            $target = substr($ref->getAttribute('href'), 1);
            if (!isset($definitions[$target])) {
                continue;
            }
            $node = $ref->parentNode instanceof \DOMElement && strtolower($ref->parentNode->tagName) === 'sup'
                ? $ref->parentNode : $ref;
            $this->marker($node, $definitions[$target], true);
        }
        // Tufte CSS toggles for small screens.
        foreach (iterator_to_array($xpath->query('//label[' . $class('margin-toggle') . ']')) as $label) {
            $label->parentNode->removeChild($label);
        }
        foreach (iterator_to_array($xpath->query('//*[' . $class('sidenote') . ']')) as $note) {
            $this->marker($note, $this->inner_html($note), true);
        }
        $query = '//aside | //*[' . $class('marginnote') . ' or ' . $class('column-margin') . ' or ' . $class('aside') . ']';
        foreach (iterator_to_array($xpath->query($query)) as $note) {
            if ($note->parentNode) {
                $this->marker($note, $this->inner_html($note), false);
            }
        }
    }

    /**
     * Replace a node by a note marker.
     *
     * Block level notes (aside, div) are moved to the end of the preceding element, so the
     * note starts next to the text it belongs to.
     *
     * @param \DOMNode $node
     * @param string $html content of the note
     * @param bool $numbered
     */
    protected function marker(\DOMNode $node, string $html, bool $numbered): void {
        $html = trim($html);
        if (!$node->parentNode) {
            return;
        }
        if (strip_tags($html, '<img>') === '') {
            $node->parentNode->removeChild($node);
            return;
        }
        $this->notes[] = ['html' => $html, 'numbered' => $numbered];
        $marker = $this->dom->createTextNode('[[bbnote:' . (count($this->notes) - 1) . ']]');
        $tag = strtolower($node->nodeName);
        if (in_array($tag, ['aside', 'div', 'section', 'figure'])) {
            $prev = $node->previousSibling;
            while ($prev && !($prev instanceof \DOMElement)) {
                $prev = $prev->previousSibling;
            }
            if ($prev && in_array(strtolower($prev->tagName), self::TEXT_TAGS)) {
                $prev->appendChild($marker);
                $node->parentNode->removeChild($node);
                return;
            }
            $p = $this->dom->createElement('p');
            $p->appendChild($marker);
            $node->parentNode->replaceChild($p, $node);
            return;
        }
        $node->parentNode->replaceChild($marker, $node);
    }

    /**
     * Inner html of an element.
     *
     * @param \DOMNode $node
     * @return string
     */
    protected function inner_html(\DOMNode $node): string {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $this->dom->saveHTML($child);
        }
        return $html;
    }

    /**
     * Replace the markers in the text blocks by note numbers and attach the notes.
     *
     * @param array $blocks
     * @return array
     */
    protected function attach_notes(array $blocks): array {
        $number = 0;
        $pending = [];
        $out = [];
        foreach ($blocks as $block) {
            if ($block['type'] !== 'text') {
                $out[] = $block;
                continue;
            }
            $notes = $pending;
            $pending = [];
            $html = preg_replace_callback('/\[\[bbnote:(\d+)\]\]/', function ($m) use (&$notes, &$number) {
                $note = $this->notes[(int)$m[1]] ?? null;
                if (!$note) {
                    return '';
                }
                $content = trim(purify_html($note['html']));
                if (!preg_match('/^<(p|ul|ol|div|table|figure|blockquote)\b/i', $content)) {
                    $content = '<p>' . $content . '</p>';
                }
                if (!$note['numbered']) {
                    $notes[] = $content;
                    return '';
                }
                $number++;
                $notes[] = preg_replace('/^<p>/i', '<p><sup>' . $number . '</sup> ', $content, 1);
                return '<sup>' . $number . '</sup>';
            }, $block['html']);
            if (trim(strip_tags($html)) === '') {
                // A block that consisted of a note only: attach it to the next text.
                $pending = $notes;
                continue;
            }
            $block['html'] = $html;
            $block['notes'] = $notes;
            $out[] = $block;
        }
        if ($pending) {
            for ($i = count($out) - 1; $i >= 0; $i--) {
                if ($out[$i]['type'] === 'text') {
                    $out[$i]['notes'] = array_merge($out[$i]['notes'] ?? [], $pending);
                    break;
                }
            }
        }
        return $out;
    }

    /**
     * Process the children of a container.
     *
     * @param \DOMNode $parent
     */
    protected function walk(\DOMNode $parent): void {
        $inline = '';
        foreach (iterator_to_array($parent->childNodes) as $node) {
            $tag = $node instanceof \DOMElement ? strtolower($node->tagName) : '';
            $isblock = in_array($tag, self::TEXT_TAGS) || in_array($tag, self::CONTAINER_TAGS)
                || in_array($tag, ['img', 'figure', 'hr', 'picture']);
            if (!$isblock) {
                // Loose text and inline elements are collected into a paragraph.
                $inline .= $node instanceof \DOMText ? s($node->textContent) : $this->dom->saveHTML($node);
                continue;
            }
            $this->flush_inline($inline);
            $inline = '';
            if (in_array($tag, self::CONTAINER_TAGS)) {
                $this->walk($node);
            } else if ($tag === 'hr') {
                $class = $node->getAttribute('class') . ' ' . $node->getAttribute('style');
                if (preg_match('/pagebreak|page-break/i', $class)) {
                    $this->blocks[] = ['type' => 'pagebreak'];
                }
            } else if ($tag === 'img' || $tag === 'picture') {
                $img = $tag === 'img' ? $node : $node->getElementsByTagName('img')->item(0);
                if ($img) {
                    $this->image($img, '');
                }
            } else if ($tag === 'figure') {
                $caption = '';
                foreach ($node->getElementsByTagName('figcaption') as $fc) {
                    $caption = trim($fc->textContent);
                }
                $imgs = iterator_to_array($node->getElementsByTagName('img'));
                foreach ($imgs as $img) {
                    $this->image($img, $caption);
                }
                if (!$imgs && $caption !== '') {
                    $this->text('p', '<p>' . s($caption) . '</p>');
                }
            } else {
                $this->text_element($node, $tag);
            }
        }
        $this->flush_inline($inline);
    }

    /**
     * Text element; contained images become separate image blocks after it.
     *
     * @param \DOMElement $node
     * @param string $tag
     */
    protected function text_element(\DOMElement $node, string $tag): void {
        $imgs = iterator_to_array($node->getElementsByTagName('img'));
        foreach ($imgs as $img) {
            $img->parentNode->removeChild($img);
        }
        if (trim($node->textContent) !== '') {
            $this->text($tag, $this->dom->saveHTML($node));
        }
        foreach ($imgs as $img) {
            $this->image($img, '');
        }
    }

    /**
     * Wrap collected inline content into a paragraph.
     *
     * @param string $inline
     */
    protected function flush_inline(string $inline): void {
        if (trim(strip_tags($inline)) !== '') {
            $this->text('p', '<p>' . trim($inline) . '</p>');
        }
    }

    /**
     * Add a text block.
     *
     * @param string $tag
     * @param string $html
     */
    protected function text(string $tag, string $html): void {
        $html = trim(purify_html($html));
        if ($html !== '') {
            $this->blocks[] = ['type' => 'text', 'tag' => $tag, 'html' => $html];
        }
    }

    /**
     * Add an image block (or its alternative text when the image is not available).
     *
     * @param \DOMElement $img
     * @param string $caption
     */
    protected function image(\DOMElement $img, string $caption): void {
        $alt = trim($img->getAttribute('alt'));
        $image = ($this->resolveimage)(trim($img->getAttribute('src')));
        $info = $image ? @getimagesizefromstring($image['data']) : false;
        if (!$info) {
            if ($alt !== '' || $caption !== '') {
                $this->text('p', '<p><em>[' . s($alt !== '' ? $alt : $caption) . ']</em></p>');
            }
            return;
        }
        $this->blocks[] = [
            'type' => 'image',
            'data' => $image['data'],
            'filename' => $image['filename'],
            'alt' => $alt,
            'caption' => $caption,
            'width' => (int)$info[0],
            'height' => (int)$info[1],
        ];
    }
}
