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
 * Scan optimisation with GD: border chopping, deskewing, shadow removal,
 * double page detection and ink saving.
 *
 * The class has no Moodle dependencies so that it can be unit tested in isolation.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class image_cleanup {
    /**
     * Create a truecolor image from binary data.
     *
     * @param string $data
     * @return \GdImage
     */
    public static function load(string $data): \GdImage {
        $img = @imagecreatefromstring($data);
        if (!$img) {
            throw new \moodle_exception('errorimage', 'mod_buchbinder');
        }
        if (!imageistruecolor($img)) {
            imagepalettetotruecolor($img);
        }
        return $img;
    }

    /**
     * Encode an image as PNG.
     *
     * @param \GdImage $img
     * @return string
     */
    public static function to_png(\GdImage $img): string {
        ob_start();
        imagepng($img, null, 6);
        return ob_get_clean();
    }

    /**
     * Luminance of a truecolor pixel value.
     *
     * @param int $rgb
     * @return int 0..255
     */
    public static function lum(int $rgb): int {
        return (int)((299 * (($rgb >> 16) & 0xFF) + 587 * (($rgb >> 8) & 0xFF) + 114 * ($rgb & 0xFF)) / 1000);
    }

    /**
     * Share of dark pixels in a row or column (sampled).
     *
     * @param \GdImage $img
     * @param bool $row true: row $index, false: column $index
     * @param int $index
     * @param int $threshold luminance below which a pixel counts as dark
     * @return float 0..1
     */
    protected static function dark_ratio(\GdImage $img, bool $row, int $index, int $threshold): float {
        $len = $row ? imagesx($img) : imagesy($img);
        $step = max(1, (int)($len / 200));
        $dark = 0;
        $total = 0;
        for ($i = 0; $i < $len; $i += $step) {
            $rgb = $row ? imagecolorat($img, $i, $index) : imagecolorat($img, $index, $i);
            if (self::lum($rgb) < $threshold) {
                $dark++;
            }
            $total++;
        }
        return $total ? $dark / $total : 0;
    }

    /**
     * Border chopping: remove black scanner margins.
     *
     * @param \GdImage $img
     * @param int $threshold
     * @param float $maxfraction never remove more than this share per edge
     * @return \GdImage
     */
    public static function chop_borders(\GdImage $img, int $threshold = 60, float $maxfraction = 0.2): \GdImage {
        $w = imagesx($img);
        $h = imagesy($img);
        $isborder = fn(bool $row, int $i) => self::dark_ratio($img, $row, $i, $threshold) > 0.6;

        $top = 0;
        while ($top < $h * $maxfraction && $isborder(true, $top)) {
            $top++;
        }
        $bottom = $h - 1;
        while ($bottom > $h * (1 - $maxfraction) && $isborder(true, $bottom)) {
            $bottom--;
        }
        $left = 0;
        while ($left < $w * $maxfraction && $isborder(false, $left)) {
            $left++;
        }
        $right = $w - 1;
        while ($right > $w * (1 - $maxfraction) && $isborder(false, $right)) {
            $right--;
        }
        if ($top === 0 && $left === 0 && $bottom === $h - 1 && $right === $w - 1) {
            return $img;
        }
        $cropped = imagecrop($img, ['x' => $left, 'y' => $top, 'width' => $right - $left + 1, 'height' => $bottom - $top + 1]);
        return $cropped ?: $img;
    }

    /**
     * Estimate the skew angle of text lines (projection profile method).
     *
     * @param \GdImage $img
     * @param float $maxangle degrees
     * @param float $step degrees
     * @return float angle in degrees; positive means lines descend to the right
     */
    public static function detect_skew(\GdImage $img, float $maxangle = 5.0, float $step = 0.25): float {
        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1, 800 / max($w, $h));
        $sw = max(1, (int)($w * $scale));
        $sh = max(1, (int)($h * $scale));
        $small = imagescale($img, $sw, $sh);
        $points = [];
        for ($y = 0; $y < $sh; $y += 2) {
            for ($x = 0; $x < $sw; $x += 2) {
                if (self::lum(imagecolorat($small, $x, $y)) < 110) {
                    $points[] = [$x, $y];
                }
            }
        }
        if (count($points) < 50) {
            return 0.0;
        }
        $best = 0.0;
        $bestscore = -1;
        for ($a = -$maxangle; $a <= $maxangle + 1e-9; $a += $step) {
            $rad = deg2rad($a);
            $sin = sin($rad);
            $cos = cos($rad);
            $bins = [];
            foreach ($points as [$x, $y]) {
                $b = (int)round($y * $cos - $x * $sin);
                $bins[$b] = ($bins[$b] ?? 0) + 1;
            }
            $score = 0;
            foreach ($bins as $c) {
                $score += $c * $c;
            }
            if ($score > $bestscore) {
                $bestscore = $score;
                $best = $a;
            }
        }
        return round($best, 2);
    }

    /**
     * Straighten a skewed scan. The result keeps the original size.
     *
     * @param \GdImage $img
     * @param float|null $angle detected automatically when null
     * @return \GdImage
     */
    public static function deskew(\GdImage $img, ?float $angle = null): \GdImage {
        $angle = $angle ?? self::detect_skew($img);
        if (abs($angle) < 0.2) {
            return $img;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $white = imagecolorallocate($img, 255, 255, 255);
        // GD rotates anticlockwise for positive angles.
        $rotated = imagerotate($img, $angle, $white);
        $rw = imagesx($rotated);
        $rh = imagesy($rotated);
        $out = imagecrop($rotated, ['x' => (int)(($rw - $w) / 2), 'y' => (int)(($rh - $h) / 2), 'width' => $w, 'height' => $h]);
        return $out ?: $rotated;
    }

    /**
     * Remove shadows and yellowing by normalising against a local background estimate.
     *
     * @param \GdImage $img
     * @param int $tile tile size in pixels
     * @return \GdImage
     */
    public static function remove_shadow(\GdImage $img, int $tile = 48): \GdImage {
        $w = imagesx($img);
        $h = imagesy($img);
        $tx = (int)ceil($w / $tile);
        $ty = (int)ceil($h / $tile);
        // Background per tile: bright percentile of sampled luminance.
        $bg = [];
        for ($j = 0; $j < $ty; $j++) {
            for ($i = 0; $i < $tx; $i++) {
                $samples = [];
                for ($y = $j * $tile; $y < min($h, ($j + 1) * $tile); $y += 4) {
                    for ($x = $i * $tile; $x < min($w, ($i + 1) * $tile); $x += 4) {
                        $samples[] = self::lum(imagecolorat($img, $x, $y));
                    }
                }
                sort($samples);
                $bg[$j][$i] = max(40, $samples[(int)(count($samples) * 0.9)] ?? 255);
            }
        }
        $out = imagecreatetruecolor($w, $h);
        for ($y = 0; $y < $h; $y++) {
            // Bilinear interpolation between tile centres.
            $fy = max(0, min($ty - 1, ($y - $tile / 2) / $tile));
            $j0 = (int)floor($fy);
            $j1 = min($ty - 1, $j0 + 1);
            $dy = $fy - $j0;
            for ($x = 0; $x < $w; $x++) {
                $fx = max(0, min($tx - 1, ($x - $tile / 2) / $tile));
                $i0 = (int)floor($fx);
                $i1 = min($tx - 1, $i0 + 1);
                $dx = $fx - $i0;
                $b = $bg[$j0][$i0] * (1 - $dx) * (1 - $dy) + $bg[$j0][$i1] * $dx * (1 - $dy)
                    + $bg[$j1][$i0] * (1 - $dx) * $dy + $bg[$j1][$i1] * $dx * $dy;
                $f = 255 / $b;
                $rgb = imagecolorat($img, $x, $y);
                $r = min(255, (int)((($rgb >> 16) & 0xFF) * $f));
                $g = min(255, (int)((($rgb >> 8) & 0xFF) * $f));
                $bl = min(255, (int)(($rgb & 0xFF) * $f));
                imagesetpixel($out, $x, $y, ($r << 16) | ($g << 8) | $bl);
            }
        }
        return $out;
    }

    /**
     * Find the gutter of a scanned double page.
     *
     * A gutter is either a shadow valley or an empty vertical band near the centre.
     * Wide figures, maps or tables cross the centre and therefore have neither,
     * so they are kept as protected landscape pages.
     *
     * @param \GdImage $img
     * @return int|null x coordinate of the gutter or null if the page is no double page
     */
    public static function find_gutter(\GdImage $img): ?int {
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w < $h * 1.15) {
            return null;
        }
        $step = max(1, (int)($h / 300));
        $means = [];
        $ink = [];
        for ($x = 0; $x < $w; $x++) {
            $sum = 0;
            $dark = 0;
            $n = 0;
            for ($y = (int)($h * 0.05); $y < $h * 0.95; $y += $step) {
                $l = self::lum(imagecolorat($img, $x, $y));
                $sum += $l;
                $dark += $l < 110 ? 1 : 0;
                $n++;
            }
            $means[$x] = $sum / max(1, $n);
            $ink[$x] = $dark / max(1, $n);
        }
        $from = (int)($w * 0.4);
        $to = (int)($w * 0.6);
        $sorted = $means;
        sort($sorted);
        $median = $sorted[(int)(count($sorted) / 2)];

        // Shadow valley.
        $minx = $from;
        for ($x = $from; $x <= $to; $x++) {
            if ($means[$x] < $means[$minx]) {
                $minx = $x;
            }
        }
        if ($median - $means[$minx] > 25) {
            return $minx;
        }

        // Empty band: widest run of ink free columns near the centre, with ink on both halves.
        $best = null;
        $bestlen = 0;
        $runstart = null;
        for ($x = $from; $x <= $to + 1; $x++) {
            $empty = $x <= $to && $ink[$x] < 0.005;
            if ($empty && $runstart === null) {
                $runstart = $x;
            } else if (!$empty && $runstart !== null) {
                if ($x - $runstart > $bestlen) {
                    $bestlen = $x - $runstart;
                    $best = (int)(($runstart + $x - 1) / 2);
                }
                $runstart = null;
            }
        }
        $leftink = array_sum(array_slice($ink, 0, $from));
        $rightink = array_sum(array_slice($ink, $to));
        if ($best !== null && $bestlen >= $w * 0.01 && $leftink > 0.5 && $rightink > 0.5) {
            return $best;
        }
        return null;
    }

    /**
     * Split a double page at the given gutter.
     *
     * @param \GdImage $img
     * @param int $gutter
     * @return \GdImage[] [left, right]
     */
    public static function split(\GdImage $img, int $gutter): array {
        $h = imagesy($img);
        $w = imagesx($img);
        $left = imagecrop($img, ['x' => 0, 'y' => 0, 'width' => $gutter, 'height' => $h]);
        $right = imagecrop($img, ['x' => $gutter, 'y' => 0, 'width' => $w - $gutter, 'height' => $h]);
        return [$left, $right];
    }

    /**
     * Average luminance of an image (sampled).
     *
     * @param \GdImage $img
     * @return float 0..255
     */
    public static function mean_lum(\GdImage $img): float {
        $w = imagesx($img);
        $h = imagesy($img);
        $step = max(1, (int)(max($w, $h) / 150));
        $sum = 0;
        $n = 0;
        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                $sum += self::lum(imagecolorat($img, $x, $y));
                $n++;
            }
        }
        return $n ? $sum / $n : 255;
    }

    /**
     * Ink saver for printing: dark pages are inverted, all pages are converted to
     * greyscale and light tints are pushed to white.
     *
     * @param \GdImage $img
     * @return \GdImage
     */
    public static function ink_saver(\GdImage $img): \GdImage {
        if (self::mean_lum($img) < 100) {
            imagefilter($img, IMG_FILTER_NEGATE);
        }
        imagefilter($img, IMG_FILTER_GRAYSCALE);
        imagefilter($img, IMG_FILTER_BRIGHTNESS, 20);
        imagefilter($img, IMG_FILTER_CONTRAST, -25);
        return $img;
    }
}
