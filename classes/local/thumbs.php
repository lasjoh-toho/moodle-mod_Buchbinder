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
 * Miniature pages: page image or the text and image frames scaled down (container query units).
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class thumbs {
    /**
     * Template context of a miniature page (template mod_buchbinder/page_thumb).
     *
     * @param document $document
     * @param \stdClass $page
     * @param \stdClass[] $overlays overlays of the page
     * @return array
     */
    public static function page(document $document, \stdClass $page, array $overlays): array {
        $frames = [];
        foreach ($overlays as $o) {
            if (!overlay_types::is_frame($o->overlaytype)) {
                continue;
            }
            $s = $o->settings;
            $box = sprintf(
                'left:%.3f%%;top:%.3f%%;width:%.3f%%;height:%.3f%%;',
                $o->x * 100,
                $o->y * 100,
                $o->w * 100,
                $o->h * 100
            );
            if ($o->overlaytype === overlay_types::TEXTFRAME) {
                $frames[] = ['istext' => true, 'isimage' => false, 'box' => $box . '--bb-scale:' . $s['fontscale'] . ';'
                    . ($s['columns'] > 1 ? 'column-count:' . $s['columns'] . ';' : ''),
                    'html' => format_text($s['html'], FORMAT_HTML, ['context' => $document->get_context(), 'filter' => false]),
                    'styleclass' => !empty($s['style']) ? 'bb-style-' . $s['style'] : ''];
            } else if ($url = $document->frame_image_url($o)) {
                $frames[] = ['isimage' => true, 'istext' => false, 'box' => $box, 'src' => $url->out(false),
                    'cover' => $s['fit'] === 'cover'];
            }
        }
        $isimage = $page->pagetype === 'image';
        return [
            'isimage' => $isimage,
            'imageurl' => $isimage ? $document->page_image_url($page)->out(false) : '',
            'ratio' => $page->height ? round($page->height / max(1, $page->width) * 100, 3) : 141.429,
            'frames' => $frames,
            'hasframes' => !empty($frames),
            'blank' => !$isimage && !$frames && $page->pagetype === 'canvas',
        ];
    }
}
