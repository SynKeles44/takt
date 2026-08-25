<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The environment a command needs to behave the way it does in a terminal.
 *
 * Takt is normally started by a login item, which passes down a bare PATH — docker, make and
 * friends are then simply not there. This puts the usual tool directories in front of it.
 */
final class ShellEnvironment
{
    /** @var list<string> */
    private const array DIRECTORIES = [
        '/opt/homebrew/bin',
        '/opt/homebrew/sbin',
        '/usr/local/bin',
    ];

    public static function path(): string
    {
        $home = (string) (getenv('HOME') ?: '');

        $candidates = [
            ...self::DIRECTORIES,
            $home === '' ? null : $home.'/.docker/bin',
            $home === '' ? null : $home.'/.local/bin',
        ];

        $found = array_filter($candidates, static fn (?string $path): bool => $path !== null && is_dir($path));

        return implode(':', [...$found, (string) (getenv('PATH') ?: '/usr/bin:/bin:/usr/sbin:/sbin')]);
    }

    /** @return array<string, string> */
    public static function variables(): array
    {
        return ['PATH' => self::path()];
    }

    /** The user's own shell, so their profile is read the way a terminal would read it. */
    public static function shell(): string
    {
        $shell = (string) (getenv('SHELL') ?: '');

        return $shell !== '' && is_executable($shell) ? $shell : '/bin/sh';
    }

    /** The absolute path of a tool, or null when this machine does not have it. */
    public static function binary(string $name): ?string
    {
        foreach (explode(':', self::path()) as $directory) {
            $candidate = rtrim($directory, '/').'/'.$name;

            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
