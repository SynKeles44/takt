<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Turns raw terminal output into something a page can show: colour codes and cursor moves
 * are dropped, and a carriage return does what it does on a terminal — it rewrites the
 * current line, which is how progress bars stay one line instead of hundreds.
 */
final class TerminalText
{
    public static function clean(string $raw): string
    {
        $text = preg_replace('/\e\][^\a\e]*(?:\a|\e\\\\)/', '', $raw) ?? $raw;      // window titles
        $text = preg_replace('/\e[@-Z\\\\-_]|\e\[[0-?]*[ -\/]*[@-~]/', '', $text) ?? $text; // CSI and friends
        $text = str_replace(["\x07", "\x00"], '', $text);

        $lines = [];

        foreach (explode("\n", str_replace("\r\n", "\n", $text)) as $line) {
            $lines[] = self::carriage($line);
        }

        return implode("\n", $lines);
    }

    /** Everything before the last carriage return was overwritten on a real terminal. */
    private static function carriage(string $line): string
    {
        if (! str_contains($line, "\r")) {
            return self::backspaces($line);
        }

        $parts = explode("\r", $line);
        $result = '';

        foreach ($parts as $part) {
            $part = self::backspaces($part);

            // a shorter piece only overwrites its own length, exactly like a terminal
            $result = strlen($part) >= strlen($result)
                ? $part
                : $part.substr($result, strlen($part));
        }

        return $result;
    }

    private static function backspaces(string $line): string
    {
        while (str_contains($line, "\x08")) {
            $replaced = preg_replace('/[^\x08]\x08/', '', $line, 1);

            if ($replaced === null || $replaced === $line) {
                return str_replace("\x08", '', $line);
            }

            $line = $replaced;
        }

        return $line;
    }
}
