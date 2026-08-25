<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Walks the home directory so a project folder can be picked in the page itself — a browser
 * never hands out an absolute path, and Takt runs on the same machine anyway.
 *
 * Everything is confined to the home directory: a path is resolved first and refused unless
 * it still sits inside it, which also settles symlinks and any ".." someone types.
 */
final class FolderBrowser
{
    private const int LIMIT = 400;

    public function home(): string
    {
        return rtrim((string) (getenv('HOME') ?: sys_get_temp_dir()), '/');
    }

    /**
     * @return array{path: string, label: string, parent: ?string, home: string, entries: list<array{name: string, path: string, git: bool}>}
     */
    public function list(?string $path = null): array
    {
        $target = $this->resolve($path) ?? $this->home();

        $entries = [];

        foreach ($this->read($target) as $name) {
            $full = $target.'/'.$name;

            $entries[] = [
                'name' => $name,
                'path' => $full,
                'git' => is_dir($full.'/.git'),
            ];
        }

        usort($entries, fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));

        return [
            'path' => $target,
            'label' => $this->shorten($target),
            'parent' => $this->parent($target),
            'home' => $this->home(),
            'entries' => array_slice($entries, 0, self::LIMIT),
        ];
    }

    /** A path is only real if it exists and stays inside the home directory. */
    public function resolve(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $expanded = str_starts_with($path, '~') ? $this->home().substr($path, 1) : $path;
        $real = realpath($expanded);

        if ($real === false || ! is_dir($real)) {
            return null;
        }

        $home = $this->home();

        return $real === $home || str_starts_with($real, $home.'/') ? $real : null;
    }

    public function shorten(string $path): string
    {
        $home = $this->home();

        return $path === $home ? '~' : ($home !== '' && str_starts_with($path, $home.'/') ? '~'.substr($path, strlen($home)) : $path);
    }

    private function parent(string $path): ?string
    {
        if ($path === $this->home()) {
            return null;
        }

        $parent = dirname($path);

        return $this->resolve($parent);
    }

    /** @return list<string> visible sub-directories, nothing else */
    private function read(string $path): array
    {
        $handle = @opendir($path);

        if ($handle === false) {
            return [];
        }

        $names = [];

        while (($name = readdir($handle)) !== false) {
            if (str_starts_with($name, '.') || ! is_dir($path.'/'.$name)) {
                continue;
            }

            $names[] = $name;
        }

        closedir($handle);

        return $names;
    }
}
