<?php

declare(strict_types=1);

namespace App\Support;

use GdImage;
use RuntimeException;

/**
 * Draws the Takt mark — bar line with two repeat dots — at any size, so the app
 * bundle icon and the notification icon come from the same source.
 */
final class AppIcon
{
    public static function supported(): bool
    {
        return function_exists('imagecreatetruecolor');
    }

    public static function write(int $size, string $path): void
    {
        if (! self::supported()) {
            throw new RuntimeException('The GD extension is required to render the icon.');
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0o755, true);
        }

        $image = self::render($size);

        imagepng($image, $path);
        imagedestroy($image);
    }

    public static function render(int $size): GdImage
    {
        /*
         * The mark: Takti in white on the brand gradient, filling a rounded tile.
         *
         * The previous version drew the violet figure on transparency and read as empty — because
         * it was: most of the frame carried nothing. A filled tile is what an icon needs to have a
         * silhouette in a dock, and a light figure on colour is the arrangement that survives being
         * shrunk to 16 pixels. Coordinates follow the SVG's 64-unit grid so the two stay in step.
         */
        $scale = $size / 64;
        $px = static fn (float $value): int => (int) round($value * $scale);

        $image = imagecreatetruecolor($size, $size);

        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagealphablending($image, true);

        // --- the tile -------------------------------------------------------
        $tile = imagecreatetruecolor($size, $size);
        $lightX = 0.16 * $size;
        $lightY = 0.06 * $size;
        $reach = 1.22 * $size;

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $ratio = min(1.0, hypot($x - $lightX, $y - $lightY) / $reach);

                [$r, $g, $b] = $ratio < 0.5
                    ? self::mix([139, 92, 246], [99, 102, 241], $ratio / 0.5)
                    : self::mix([99, 102, 241], [67, 56, 202], ($ratio - 0.5) / 0.5);

                imagesetpixel($tile, $x, $y, imagecolorallocate($tile, $r, $g, $b));
            }
        }

        $mask = imagecreatetruecolor($size, $size);
        imagealphablending($mask, false);
        imagesavealpha($mask, true);
        imagefill($mask, 0, 0, imagecolorallocatealpha($mask, 0, 0, 0, 127));
        self::roundedRect($mask, $px(1), $px(1), $px(63), $px(63), $px(15), imagecolorallocate($mask, 255, 255, 255));

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ((imagecolorat($mask, $x, $y) >> 24 & 0x7F) !== 127) {
                    imagesetpixel($image, $x, $y, imagecolorat($tile, $x, $y));
                }
            }
        }

        imagedestroy($tile);
        imagedestroy($mask);

        /*
         * Gloss and shadow are computed per pixel, not stacked as ellipses. Stacking looked
         * obvious and produced visible concentric rings: each filled ellipse blends against the
         * previous one, so the overlaps accumulate into bands exactly where the gradient was
         * supposed to be smooth. A falloff evaluated per pixel has no steps to band.
         */
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ((imagecolorat($image, $x, $y) >> 24 & 0x7F) === 127) {
                    continue;
                }

                // the gloss: an ellipse of light centred above the tile's top edge
                $gx = ($x - 0.5 * $size) / (0.62 * $size);
                $gy = ($y - 0.02 * $size) / (0.34 * $size);
                $gloss = max(0.0, 1 - ($gx * $gx + $gy * $gy));

                // the shadow Takti casts, which is what lifts him off the tile
                $sx = ($x - 0.5 * $size) / (0.30 * $size);
                $sy = ($y - 0.81 * $size) / (0.085 * $size);
                $shadow = max(0.0, 1 - ($sx * $sx + $sy * $sy));

                if ($gloss <= 0 && $shadow <= 0) {
                    continue;
                }

                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                if ($gloss > 0) {
                    $w = 0.30 * $gloss * $gloss;
                    [$r, $g, $b] = self::mix([$r, $g, $b], [255, 255, 255], $w);
                }

                if ($shadow > 0) {
                    $w = 0.38 * $shadow * $shadow;
                    [$r, $g, $b] = self::mix([$r, $g, $b], [26, 16, 53], $w);
                }

                imagesetpixel($image, $x, $y, imagecolorallocate($image, $r, $g, $b));
            }
        }

        // --- the figure, in white -------------------------------------------
        $white = imagecolorallocate($image, 255, 255, 255);

        self::thickLine($image, $px(18), $px(34), $px(12.5), $px(39), $px(2.2), $white);
        self::thickLine($image, $px(46), $px(34), $px(51.5), $px(39), $px(2.2), $white);

        imagefilledellipse($image, $px(25), $px(50), $px(10.8), $px(6), $white);
        imagefilledellipse($image, $px(39), $px(50), $px(10.8), $px(6), $white);

        self::roundedRect($image, $px(15), $px(14), $px(49), $px(48), $px(11.5), $white);
        self::thickLine($image, $px(32), $px(14), $px(36.5), $px(8.5), $px(1.6), $white);

        // --- the face, in the deep brand tone rather than black -------------
        $ink = imagecolorallocate($image, 55, 48, 163);

        imagefilledellipse($image, $px(25.5), $px(30), $px(6.6), $px(6.6), $ink);
        imagefilledellipse($image, $px(38.5), $px(30), $px(6.6), $px(6.6), $ink);

        self::thickLine($image, $px(28), $px(38.5), $px(30.7), $px(40.1), $px(1.15), $ink);
        self::thickLine($image, $px(30.7), $px(40.1), $px(33.3), $px(40.1), $px(1.15), $ink);
        self::thickLine($image, $px(33.3), $px(40.1), $px(36), $px(38.5), $px(1.15), $ink);

        $spark = imagecolorallocatealpha($image, 255, 255, 255, 20);

        imagefilledellipse($image, $px(26.6), $px(28.8), $px(2.1), $px(2.1), $spark);
        imagefilledellipse($image, $px(39.6), $px(28.8), $px(2.1), $px(2.1), $spark);

        return $image;
    }

    /**
     * Linear blend between two RGB triples.
     *
     * @param  array{int, int, int}  $from
     * @param  array{int, int, int}  $to
     * @return array{int, int, int}
     */
    private static function mix(array $from, array $to, float $t): array
    {
        $t = max(0.0, min(1.0, $t));

        return [
            (int) round($from[0] + ($to[0] - $from[0]) * $t),
            (int) round($from[1] + ($to[1] - $from[1]) * $t),
            (int) round($from[2] + ($to[2] - $from[2]) * $t),
        ];
    }

    /** A line with a round cap at each end — GD only draws hairlines and squares. */
    private static function thickLine(GdImage $image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
        $steps = max(1, (int) round(hypot($x2 - $x1, $y2 - $y1)));

        for ($i = 0; $i <= $steps; $i++) {
            $t = $i / $steps;

            imagefilledellipse(
                $image,
                (int) round($x1 + ($x2 - $x1) * $t),
                (int) round($y1 + ($y2 - $y1) * $t),
                $radius * 2,
                $radius * 2,
                $color,
            );
        }
    }

    private static function roundedRect(GdImage $image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
        $radius = max(1, $radius);

        imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
        imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);

        foreach ([[$x1 + $radius, $y1 + $radius], [$x2 - $radius, $y1 + $radius], [$x1 + $radius, $y2 - $radius], [$x2 - $radius, $y2 - $radius]] as [$cx, $cy]) {
            imagefilledellipse($image, $cx, $cy, $radius * 2, $radius * 2, $color);
        }
    }
}
