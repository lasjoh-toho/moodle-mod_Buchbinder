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
 * Content harvester: fetches web pages server side (proxy function) and extracts
 * text, tables and images of a selected element.
 *
 * Requests go through Moodle's curl class, so the site proxy and the
 * "cURL blocked hosts" security settings apply.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class harvester {
    /** @var int Maximum number of images copied from one snippet. */
    const MAX_IMAGES = 20;

    /**
     * Whether the admin enabled the server side proxy.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return (bool)get_config('buchbinder', 'enableharvester');
    }

    /**
     * Fetch a URL with the site's security settings.
     *
     * @param string $url
     * @param int $maxbytes
     * @return array [content, content type, effective url]
     */
    protected static function get(string $url, int $maxbytes): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $timeout = max(1, (int)get_config('buchbinder', 'harvestertimeout') ?: 15);
        $curl = new \curl();
        $content = $curl->get($url, [], [
            'CURLOPT_TIMEOUT' => $timeout,
            'CURLOPT_CONNECTTIMEOUT' => $timeout,
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_MAXREDIRS' => 3,
        ]);
        $info = $curl->get_info();
        if ($curl->get_errno() || empty($info['http_code']) || $info['http_code'] >= 400) {
            throw new \moodle_exception('errorharvest', 'mod_buchbinder', '', s($curl->error ?: ($info['http_code'] ?? '')));
        }
        if ($maxbytes > 0 && strlen($content) > $maxbytes) {
            throw new \moodle_exception('errorfiletoolarge', 'mod_buchbinder', '', display_size($maxbytes));
        }
        return [$content, (string)($info['content_type'] ?? ''), (string)($info['url'] ?? $url)];
    }

    /**
     * Resolve a relative URL.
     *
     * @param string $base
     * @param string $rel
     * @return string|null
     */
    public static function absolute_url(string $base, string $rel): ?string {
        $rel = trim($rel);
        if ($rel === '' || str_starts_with($rel, 'data:') || str_starts_with($rel, 'javascript:')) {
            return null;
        }
        if (preg_match('#^https?://#i', $rel)) {
            return $rel;
        }
        $b = parse_url($base);
        if (empty($b['scheme']) || empty($b['host'])) {
            return null;
        }
        $origin = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
        if (str_starts_with($rel, '//')) {
            return $b['scheme'] . ':' . $rel;
        }
        if (str_starts_with($rel, '/')) {
            return $origin . $rel;
        }
        if (str_starts_with($rel, '#') || str_starts_with($rel, '?')) {
            return $base . $rel;
        }
        $dir = preg_replace('#/[^/]*$#', '/', $b['path'] ?? '/');
        $path = $dir . $rel;
        // Normalise ./ and ../ segments.
        $segments = [];
        foreach (explode('/', $path) as $seg) {
            if ($seg === '..') {
                array_pop($segments);
            } else if ($seg !== '.') {
                $segments[] = $seg;
            }
        }
        return $origin . implode('/', $segments);
    }

    /**
     * Translate a simple CSS selector (tag, #id, .class, tag.class) into XPath.
     *
     * @param string $selector
     * @return string|null
     */
    public static function selector_to_xpath(string $selector): ?string {
        $selector = trim($selector);
        if ($selector === '') {
            return null;
        }
        if (!preg_match('/^([a-z][a-z0-9]*)?(?:#([\w-]+)|\.([\w-]+))?$/i', $selector, $m) || $selector === '') {
            return null;
        }
        $tag = !empty($m[1]) ? strtolower($m[1]) : '*';
        if (!empty($m[2])) {
            return "//{$tag}[@id='{$m[2]}']";
        }
        if (!empty($m[3])) {
            return "//{$tag}[contains(concat(' ', normalize-space(@class), ' '), ' {$m[3]} ')]";
        }
        return "//{$tag}";
    }

    /**
     * Harvest a snippet.
     *
     * @param string $url
     * @param string $selector optional simple CSS selector of the element to extract
     * @param int $maxbytes
     * @return array ['title' => string, 'url' => string, 'html' => string, 'images' => [filename => content]]
     */
    public static function harvest(string $url, string $selector, int $maxbytes): array {
        if (!self::is_enabled()) {
            throw new \moodle_exception('harvesterdisabled', 'mod_buchbinder');
        }
        if (!preg_match('#^https?://#i', $url)) {
            throw new \moodle_exception('errorharvest', 'mod_buchbinder', '', s($url));
        }
        [$content, $contenttype, $effectiveurl] = self::get($url, $maxbytes);

        $charset = 'UTF-8';
        if (preg_match('/charset=([\w-]+)/i', $contenttype, $m)) {
            $charset = $m[1];
        } else if (preg_match('/<meta[^>]+charset=["\']?([\w-]+)/i', $content, $m)) {
            $charset = $m[1];
        }
        if (strtoupper($charset) !== 'UTF-8') {
            $content = \core_text::convert($content, $charset, 'UTF-8');
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $content, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        $title = trim((string)$xpath->evaluate('string(//title)'));
        $query = self::selector_to_xpath($selector);
        $root = null;
        foreach (array_filter([$query, '//article', '//main', '//body']) as $q) {
            $root = $xpath->query($q)->item(0);
            if ($root) {
                break;
            }
        }
        if (!$root) {
            throw new \moodle_exception('errorharvestempty', 'mod_buchbinder');
        }

        foreach (['script', 'style', 'noscript', 'iframe', 'object', 'embed', 'form', 'svg', 'template'] as $tag) {
            foreach (iterator_to_array($xpath->query('.//' . $tag, $root)) as $node) {
                $node->parentNode->removeChild($node);
            }
        }
        foreach (iterator_to_array($xpath->query('.//a[@href]', $root)) as $a) {
            $abs = self::absolute_url($effectiveurl, $a->getAttribute('href'));
            $abs ? $a->setAttribute('href', $abs) : $a->removeAttribute('href');
        }

        $images = [];
        $harvestimages = (bool)get_config('buchbinder', 'harvestimages');
        foreach (iterator_to_array($xpath->query('.//img', $root)) as $i => $img) {
            $src = self::absolute_url($effectiveurl, $img->getAttribute('src') ?: $img->getAttribute('data-src'));
            $keep = false;
            if ($harvestimages && $src && count($images) < self::MAX_IMAGES) {
                try {
                    [$data] = self::get($src, $maxbytes);
                    $info = @getimagesizefromstring($data);
                    if ($info && in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_WEBP])) {
                        $filename = 'img' . ($i + 1) . image_type_to_extension($info[2]);
                        $images[$filename] = $data;
                        $img->setAttribute('src', '@@PLUGINFILE@@/' . $filename);
                        foreach (['srcset', 'data-src', 'sizes', 'loading'] as $attr) {
                            $img->removeAttribute($attr);
                        }
                        $keep = true;
                    }
                } catch (\moodle_exception $e) {
                    $keep = false;
                }
            }
            if (!$keep) {
                $alt = trim($img->getAttribute('alt'));
                $replacement = $alt !== '' ? $dom->createElement('em', '[' . $alt . ']') : $dom->createTextNode('');
                $img->parentNode->replaceChild($replacement, $img);
            }
        }

        $html = $dom->saveHTML($root);
        return [
            'title' => $title,
            'url' => $effectiveurl,
            'html' => purify_html($html),
            'images' => $images,
        ];
    }
}
