<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;

/**
 * Reads the targets out of a project's Makefile, so they can be listed and run from Takt.
 * A target is only ever run by name from this list — nothing the user types reaches a shell.
 */
final class MakeTargets
{
    /** `target: ## what it does` is the convention nearly every Makefile with a help target uses. */
    private const string TARGET = '/^([a-zA-Z][a-zA-Z0-9_.\/-]*)\s*:(?!=)[^=]*?(?:##\s*(.*))?$/';

    private const int LIMIT = 60;

    /** @return list<array{name: string, description: ?string}> */
    public function forProject(Project $project): array
    {
        $file = $this->file($project);

        return $file === null ? [] : $this->parse((string) file_get_contents($file));
    }

    public function has(Project $project, string $target): bool
    {
        foreach ($this->forProject($project) as $entry) {
            if ($entry['name'] === $target) {
                return true;
            }
        }

        return false;
    }

    public function file(Project $project): ?string
    {
        if (! $project->exists()) {
            return null;
        }

        foreach (['Makefile', 'makefile', 'GNUmakefile'] as $name) {
            $path = $project->absolutePath().'/'.$name;

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /** @return list<array{name: string, description: ?string}> */
    private function parse(string $contents): array
    {
        $targets = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            // recipes are indented, so a line starting with a tab is never a target
            if ($line === '' || str_starts_with($line, "\t") || str_starts_with(ltrim($line), '#')) {
                continue;
            }

            if (preg_match(self::TARGET, rtrim($line), $match) !== 1) {
                continue;
            }

            $name = $match[1];

            // special targets and pattern rules are not something to run
            if (str_starts_with($name, '.') || str_contains($name, '%') || isset($targets[$name])) {
                continue;
            }

            $description = isset($match[2]) ? trim($match[2]) : '';

            $targets[$name] = [
                'name' => $name,
                'description' => $description === '' ? null : $description,
            ];
        }

        return array_slice(array_values($targets), 0, self::LIMIT);
    }
}
