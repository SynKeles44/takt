<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * What of the checkout goes into the self-contained app, and what stays behind.
 *
 * The stage starts from what git sees, so nothing ignored — no .env, no database, no
 * node_modules — can slip in by accident. What is tracked but belongs to development is then
 * taken out: tests, the shell source, Docker, the frontend sources (the built assets are copied
 * in separately), the scripts that assume a checkout.
 */
final class ReleaseStage
{
    /** @var list<string> paths relative to the project root that a shipped app does not carry */
    public const array LEAVE_BEHIND = [
        '.dockerignore',
        '.editorconfig',
        '.gitattributes',
        '.github',
        '.gitignore',
        '.npmrc',
        'Dockerfile',
        'compose.yaml',
        'desktop',
        'docker',
        'docs',
        'install.sh',
        'package-lock.json',
        'package.json',
        'phpunit.xml',
        'resources/css',
        'resources/js',
        'tests',
        'update.sh',
        'vite.config.js',
    ];

    /** @var list<string> what the running app needs beyond the tracked tree */
    public const array BRING_ALONG = [
        'public/build',
        'public/icons',
    ];

    public static function prune(string $stage): void
    {
        foreach (self::LEAVE_BEHIND as $relative) {
            $path = $stage.'/'.$relative;

            if (is_dir($path)) {
                File::deleteDirectory($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * The working tree as git sees it, copied into the stage: every tracked file plus every new one
     * that is not ignored. Ignored is what must never ship — .env, the database, node_modules,
     * vendor — and git already knows that list better than any copy of it here would.
     */
    public static function export(string $root, string $stage): bool
    {
        $listing = Process::path($root)->timeout(60)->run(['git', 'ls-files', '-z', '--cached', '--others', '--exclude-standard']);

        if ($listing->failed()) {
            return false;
        }

        File::ensureDirectoryExists($stage);

        foreach (array_filter(explode("\0", $listing->output())) as $relative) {
            $source = $root.'/'.$relative;

            // a tracked file deleted in the working tree is still listed, and simply not shipped
            if (! is_file($source)) {
                continue;
            }

            File::ensureDirectoryExists(dirname($stage.'/'.$relative));
            copy($source, $stage.'/'.$relative);

            if (is_executable($source)) {
                chmod($stage.'/'.$relative, 0o755);
            }
        }

        return true;
    }
}
