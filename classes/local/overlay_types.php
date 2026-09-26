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
 * Definition and validation of overlay types.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overlay_types {
    /** @var string Color matched text written onto the page (type-over). */
    const TEXTBOX = 'textbox';
    /** @var string Whiteout / blackout of solutions or sensitive data. */
    const MASK = 'mask';
    /** @var string Invisible audio hotspot. */
    const AUDIO = 'audio';
    /** @var string Tap to reveal glossary term. */
    const GLOSSARY = 'glossary';
    /** @var string Text block for the smartphone HTML reflow. */
    const REFLOW = 'reflow';
    /** @var string Column region for zoom anchoring. */
    const COLUMN = 'column';

    /**
     * All overlay types.
     *
     * @return string[]
     */
    public static function all(): array {
        return [self::TEXTBOX, self::MASK, self::AUDIO, self::GLOSSARY, self::REFLOW, self::COLUMN];
    }

    /**
     * Normalise a colour value.
     *
     * @param mixed $value
     * @param string $default
     * @return string
     */
    protected static function color($value, string $default): string {
        $value = is_string($value) ? trim($value) : '';
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $default;
    }

    /**
     * Validate and whitelist the settings of an overlay.
     *
     * @param string $type
     * @param array $data raw data
     * @return array clean data
     */
    public static function clean(string $type, array $data): array {
        $text = fn($key) => clean_param((string)($data[$key] ?? ''), PARAM_TEXT);
        switch ($type) {
            case self::TEXTBOX:
                return [
                    'text' => $text('text'),
                    'fontsize' => max(6, min(96, (int)($data['fontsize'] ?? 16))),
                    'bold' => !empty($data['bold']),
                    'color' => self::color($data['color'] ?? '', '#000000'),
                    'bgcolor' => self::color($data['bgcolor'] ?? '', '#ffffff'),
                    'align' => in_array($data['align'] ?? '', ['left', 'center', 'right']) ? $data['align'] : 'left',
                ];
            case self::MASK:
                return [
                    'style' => ($data['style'] ?? '') === 'black' ? 'black' : 'white',
                    'revealable' => !empty($data['revealable']),
                    'label' => $text('label'),
                ];
            case self::AUDIO:
                return [
                    'mode' => ($data['mode'] ?? '') === 'tts' ? 'tts' : 'file',
                    'ttstext' => $text('ttstext'),
                    'ttslang' => preg_match('/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/', (string)($data['ttslang'] ?? ''))
                        ? $data['ttslang'] : 'de-DE',
                    'label' => $text('label'),
                    'filename' => clean_param((string)($data['filename'] ?? ''), PARAM_FILE),
                ];
            case self::GLOSSARY:
                return [
                    'glossaryid' => (int)($data['glossaryid'] ?? 0),
                    'term' => $text('term'),
                ];
            case self::REFLOW:
                return [
                    'text' => $text('text'),
                    'role' => in_array($data['role'] ?? '', ['p', 'h2', 'h3', 'li']) ? $data['role'] : 'p',
                ];
            case self::COLUMN:
                return [];
        }
        throw new \invalid_parameter_exception('Unknown overlay type ' . $type);
    }
}
