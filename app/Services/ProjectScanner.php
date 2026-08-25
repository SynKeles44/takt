<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Reads what a project folder already tells us, so registering it is one click:
 * name, remote, a sensible start command and — where it is written down — the port.
 */
final class ProjectScanner
{
    public function scan(string $path): array
    {
        $absolute = rtrim(str_replace('~', (string) getenv('HOME'), trim($path)), '/');

        if ($absolute === '' || ! is_dir($absolute)) {
            return ['found' => false];
        }

        $command = $this->startCommand($absolute);

        return [
            'found' => true,
            'path' => $this->shorten($absolute),
            'name' => basename($absolute),
            'repository' => $this->repository($absolute),
            'start_command' => $command,
            'port' => $this->port($absolute, $command),
            'git' => is_dir($absolute.'/.git'),
        ];
    }

    private function shorten(string $path): string
    {
        $home = (string) getenv('HOME');

        return $home !== '' && str_starts_with($path, $home) ? '~'.substr($path, strlen($home)) : $path;
    }

    private function repository(string $path): ?string
    {
        if (! is_dir($path.'/.git')) {
            return null;
        }

        $result = Process::timeout(5)->run(['git', '-C', $path, 'remote', 'get-url', 'origin']);

        if ($result->failed()) {
            return null;
        }

        $url = trim($result->output());

        if ($url === '') {
            return null;
        }

        return trim(Str::of($url)
            ->replaceMatches('#^(https?://[^/]+/|git@[^:]+:)#', '')
            ->replaceMatches('#\.git$#', '')
            ->toString(), '/');
    }

    /** `make start` when there is a Makefile with that target, otherwise the obvious one. */
    private function startCommand(string $path): ?string
    {
        if ($this->makeTarget($path, 'start')) {
            return 'make start';
        }

        if ($this->makeTarget($path, 'dev')) {
            return 'make dev';
        }

        if (is_file($path.'/docker-compose.yml') || is_file($path.'/compose.yml')) {
            return 'docker compose up';
        }

        $scripts = $this->packageScripts($path);

        foreach (['dev', 'start', 'serve'] as $script) {
            if (isset($scripts[$script])) {
                return 'npm run '.$script;
            }
        }

        if (is_file($path.'/artisan')) {
            return 'php artisan serve';
        }

        return 'make start';
    }

    private function makeTarget(string $path, string $target): bool
    {
        foreach (['Makefile', 'makefile'] as $file) {
            if (! is_file($path.'/'.$file)) {
                continue;
            }

            if (preg_match('/^'.preg_quote($target, '/').':/m', (string) file_get_contents($path.'/'.$file)) === 1) {
                return true;
            }
        }

        return false;
    }

    private function packageScripts(string $path): array
    {
        if (! is_file($path.'/package.json')) {
            return [];
        }

        $package = json_decode((string) file_get_contents($path.'/package.json'), true);

        return is_array($package['scripts'] ?? null) ? $package['scripts'] : [];
    }

    /** Only what the project states itself — the port stays optional. */
    private function port(string $path, ?string $command): ?int
    {
        foreach (['Makefile', 'makefile'] as $file) {
            if (! is_file($path.'/'.$file)) {
                continue;
            }

            if (preg_match('/^PORT\s*\??=\s*(\d{2,5})/m', (string) file_get_contents($path.'/'.$file), $match) === 1) {
                return (int) $match[1];
            }
        }

        if (is_file($path.'/.env') && preg_match('#^APP_URL=.*?:(\d{2,5})#m', (string) file_get_contents($path.'/.env'), $match) === 1) {
            return (int) $match[1];
        }

        foreach (['vite.config.js', 'vite.config.ts'] as $file) {
            if (is_file($path.'/'.$file) && preg_match('/port:\s*(\d{2,5})/', (string) file_get_contents($path.'/'.$file), $match) === 1) {
                return (int) $match[1];
            }
        }

        return match (true) {
            $command === 'npm run dev' => 5173,
            $command === 'php artisan serve' => 8000,
            default => null,
        };
    }
}
