<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A colour per person, derived from the name rather than stored.
 *
 * Derived, because the alternative is a table of people this app does not own — Linear knows who
 * they are and this is a reader. A hash into a fixed palette gives the same person the same colour
 * on every page and in every session, which is the whole point: the eye learns "the green one
 * wrote this" long before it reads the name.
 *
 * The palette is hand-picked rather than a hue rotation. Evenly spaced hues at one lightness
 * produce two yellows nobody can tell apart and a blue that disappears into a dark surface.
 */
final class Palette
{
    /** @var list<array{string, string}> background, and the ink that stays readable on it */
    private const array COLOURS = [
        ['#8b7cf6', '#ffffff'],
        ['#22c3a6', '#06251f'],
        ['#f0883e', '#2a1403'],
        ['#e05c8a', '#ffffff'],
        ['#4aa3f0', '#04203a'],
        ['#c3a62b', '#241e02'],
        ['#7ec14a', '#132605'],
        ['#9d7ce0', '#ffffff'],
        ['#50b8c9', '#032429'],
        ['#e2715c', '#2b0c06'],
    ];

    /** @return array{background: string, ink: string, initials: string} */
    public static function forName(?string $name): array
    {
        $label = trim((string) $name) ?: '?';
        [$background, $ink] = self::COLOURS[abs(crc32(mb_strtolower($label))) % count(self::COLOURS)];

        // two letters from two words, one word gives two of its own — the shape read at twenty pixels
        $parts = array_values(array_filter(preg_split('/[\s._-]+/', $label) ?: []));

        return [
            'background' => $background,
            'ink' => $ink,
            'initials' => mb_strtoupper(count($parts) > 1
                ? mb_substr($parts[0], 0, 1).mb_substr((string) end($parts), 0, 1)
                : mb_substr($label, 0, 2)),
        ];
    }
}
