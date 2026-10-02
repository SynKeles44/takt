<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\DataDirectory;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Artisan;

/**
 * The first-run work of a bundled Takt, and the no-op of every run after it.
 *
 * The app shell calls this before it starts the server: the data folder exists, the environment
 * file carries a key and the address, the database is migrated. Everything is idempotent, so a
 * launch costs a few milliseconds once the first one has done the work.
 */
class PrepareCommand extends Command
{
    protected $signature = 'takt:prepare
                            {--port=8000 : The port the server listens on}
                            {--name=Takt : The app name — the shell passes its bundle name}';

    protected $description = 'Make the data folder of a bundled Takt ready: environment, key, database';

    public function handle(): int
    {
        $data = DataDirectory::fromEnvironment();

        if ($data === null) {
            $this->components->error(DataDirectory::ENV.' is not set — this command belongs to the bundled app; a checkout uses takt:setup.');

            return self::FAILURE;
        }

        $data->prepare();

        if ($this->environment($data->environmentFile(), (int) $this->option('port'), (string) ($this->option('name') ?: 'Takt'))) {
            $this->components->twoColumnDetail('Environment', 'written');
        }

        Artisan::call('migrate', ['--force' => true], $this->output);

        $this->components->twoColumnDetail('Data', $data->path);

        return self::SUCCESS;
    }

    /** Writes the environment file once and leaves an existing one alone. */
    private function environment(string $file, int $port, string $name): bool
    {
        if (is_file($file) && str_contains((string) file_get_contents($file), 'APP_KEY=base64:')) {
            return false;
        }

        $key = 'base64:'.base64_encode(Encrypter::generateKey((string) config('app.cipher')));

        $lines = [
            'APP_NAME='.$name,
            'APP_ENV=production',
            'APP_KEY='.$key,
            'APP_DEBUG=false',
            'APP_URL=http://localhost:'.$port,
            'APP_TIMEZONE=Europe/Berlin',
            'APP_LOCALE=de',
            'APP_FALLBACK_LOCALE=en',
            'LOG_CHANNEL=daily',
            'LOG_LEVEL=warning',
            'DB_CONNECTION=sqlite',
            'SESSION_DRIVER=database',
            'CACHE_STORE=database',
            'QUEUE_CONNECTION=database',
        ];

        file_put_contents($file, implode(PHP_EOL, $lines).PHP_EOL);

        // this process was configured before the file existed; the server that follows reads it fresh
        config(['app.key' => $key, 'app.url' => 'http://localhost:'.$port]);

        return true;
    }
}
