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
        $scale = $size / 64;
        $image = imagecreatetruecolor($size, $size);

        imagealphablending($image, true);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));

        for ($y = 0; $y < $size; $y++) {
            $ratio = $y / max(1, $size - 1);

            imageline($image, 0, $y, $size, $y, imagecolorallocate(
                $image,
                (int) round(139 + (79 - 139) * $ratio),
                (int) round(92 + (70 - 92) * $ratio),
                (int) round(246 + (229 - 246) * $ratio),
            ));
        }

        $mask = imagecreatetruecolor($size, $size);
        imagealphablending($mask, false);
        imagesavealpha($mask, true);
        imagefill($mask, 0, 0, imagecolorallocatealpha($mask, 0, 0, 0, 127));

        self::roundedRect($mask, 0, 0, $size - 1, $size - 1, (int) round(16 * $scale), imagecolorallocate($mask, 255, 255, 255));

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ((imagecolorat($mask, $x, $y) >> 24 & 0x7F) === 127) {
                    imagesetpixel($image, $x, $y, imagecolorallocatealpha($image, 0, 0, 0, 127));
                }
            }
        }

        imagedestroy($mask);

        $ink = imagecolorallocate($image, 255, 255, 255);
        $soft = imagecolorallocatealpha($image, 255, 255, 255, 36);
        $dot = (int) round(7.6 * $scale);

        imagefilledellipse($image, (int) round(20 * $scale), (int) round(26 * $scale), $dot, $dot, $ink);
        imagefilledellipse($image, (int) round(20 * $scale), (int) round(38 * $scale), $dot, $dot, $ink);

        self::roundedRect($image, (int) (28.5 * $scale), (int) (16 * $scale), (int) (33 * $scale), (int) (48 * $scale), (int) (2.25 * $scale), $soft);
        self::roundedRect($image, (int) (38 * $scale), (int) (16 * $scale), (int) (48 * $scale), (int) (48 * $scale), (int) (4 * $scale), $ink);

        return $image;
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
