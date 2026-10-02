<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Whether the compiled assets are older than the sources they were built from.
 *
 * `public/build` is not in version control, and nothing rebuilt it on `git pull`: a second Mac
 * kept serving a stylesheet from weeks earlier, and the bugs that produced were impossible to
 * tell from real ones. The check costs a handful of stat calls and is read once per request.
 */
final class BuildFreshness
{
    /** @param list<string> $sources */
    public function __construct(
        private readonly string $manifest,
        private readonly array $sources,
    ) {}

    public static function forApp(): self
    {
        return new self(public_path('build/manifest.json'), [
            resource_path('css'),
            resource_path('js'),
            base_path('vite.config.js'),
            base_path('package-lock.json'),
        ]);
    }

    public function stale(): bool
    {
        if (! is_file($this->manifest)) {
            return true;
        }

        return $this->newestSource() > (int) filemtime($this->manifest);
    }

    private function newestSource(): int
    {
        $newest = 0;

        foreach ($this->sources as $source) {
            if (is_file($source)) {
                $newest = max($newest, (int) filemtime($source));

                continue;
            }

            if (! is_dir($source)) {
                continue;
            }

            foreach (File::allFiles($source) as $file) {
                $newest = max($newest, $file->getMTime());
            }
        }

        return $newest;
    }
}
