<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\LocalUrl;
use Illuminate\Console\Command;

/**
 * Everything between a fresh copy and a running Takt, in one command: environment, key,
 * database, icons, the name it answers on, the macOS app, and the login item that keeps
 * the server up. Called by install.sh, and safe to run again at any time.
 */
class SetupCommand extends Command
{
    protected $signature = 'takt:setup
                            {--host= : The name Takt answers on, default local.takt.de}
                            {--port=8000 : The port the server listens on}
                            {--no-app : Skip the macOS app bundle}
                            {--no-autostart : Skip the login item}
                            {--hosts-file= : Where the host entries live, default /etc/hosts}
                            {--env-file= : Which .env to write, default the project one}';

    protected $description = 'Make a fresh copy ready to use — environment, database, name, app, login item';

    public function handle(): int
    {
        $host = (string) ($this->option('host') ?: LocalUrl::DEFAULT_HOST);
        $port = (int) $this->option('port');

        $this->components->info('Setting Takt up.');

        $this->environment();
        $this->reloadEnv();
        $this->database();

        $this->call('takt:icons');
        $this->call('takt:hostname', array_filter([
            'host' => $host,
            '--port' => $port,
            '--hosts-file' => $this->option('hosts-file'),
            '--env-file' => $this->option('env-file'),
        ]));

        $mac = PHP_OS_FAMILY === 'Darwin';

        if ($mac && ! $this->option('no-app')) {
            $this->call('takt:app', ['--port' => $port, '--force' => true]);
        }

        if ($mac && ! $this->option('no-autostart')) {
            $this->call('takt:autostart', ['--port' => $port]);
        }

        // whatever the hostname step settled on — it stays on localhost until the name resolves
        $url = (string) config('app.url');

        $this->newLine();
        $this->components->twoColumnDetail('<fg=green;options=bold>Ready</>', $url);
        $this->components->twoColumnDetail('Folder', base_path());

        if ($mac) {
            $this->components->twoColumnDetail('App', str_replace(getenv('HOME') ?: '', '~', $this->bundle()));
        }

        $this->components->info('Open it and create your account — Takt runs entirely on this machine.');

        if ($mac && $this->option('no-autostart')) {
            $this->components->twoColumnDetail('Start the server with', 'make start');
        }

        return self::SUCCESS;
    }

    private function environment(): void
    {
        if (! is_file(base_path('.env'))) {
            copy(base_path('.env.example'), base_path('.env'));
            $this->components->twoColumnDetail('.env', 'created from .env.example');
        }

        if (! str_contains((string) file_get_contents(base_path('.env')), 'APP_KEY=base64:')) {
            $this->call('key:generate', ['--force' => true]);
        }
    }

    /**
     * A copied .env is not in the config of this process yet, and the steps below need the
     * name and the address — without this a fresh install would build "Laravel.app".
     */
    private function reloadEnv(): void
    {
        $file = base_path('.env');

        if (! is_file($file)) {
            return;
        }

        foreach (preg_split('/\R/', (string) file_get_contents($file)) ?: [] as $line) {
            if (! str_contains($line, '=') || str_starts_with(trim($line), '#')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $value = trim(trim($value), '"\'');

            match (trim($key)) {
                'APP_NAME' => config(['app.name' => $value]),
                'APP_URL' => config(['app.url' => $value]),
                default => null,
            };
        }
    }

    private function database(): void
    {
        $database = database_path('database.sqlite');

        if (! is_file($database)) {
            touch($database);
            $this->components->twoColumnDetail('Database', 'created '.basename($database));
        }

        $this->call('migrate', ['--force' => true]);
    }

    private function bundle(): string
    {
        return (getenv('HOME') ?: '').'/Applications/'.config('app.name').'.app';
    }
}
