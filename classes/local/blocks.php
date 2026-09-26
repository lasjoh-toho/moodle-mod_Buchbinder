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

    /**
     * Split html into blocks.
     *
     * @param string $html
     * @param callable|null $resolveimage returns image data for a src or null
     * @return array blocks
     */
    public static function from_html(string $html, ?callable $resolveimage = null): array {
        $splitter = new self();
        $splitter->resolveimage = $resolveimage ?? fn($src) => null;
        return $splitter->split($html);
    }

    /**
     * Split Markdown (Markdown Extra) into blocks.
     *
     * @param string $markdown
     * @param callable|null $resolveimage
     * @return array
     */
    public static function from_markdown(string $markdown, ?callable $resolveimage = null): array {
        // Quarto/Pandoc page breaks and "\newpage".
        $markdown = preg_replace(
            '/^\s*(\{\{<\s*pagebreak\s*>\}\}|\\\\newpage)\s*$/m',
            "\n<hr class=\"bb-pagebreak\">\n",
            $markdown
        );
        return self::from_html(markdown_to_html($markdown), $resolveimage);
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
        $this->walk($body);
        return $this->blocks;
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
