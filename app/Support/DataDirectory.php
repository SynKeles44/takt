<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Where a bundled Takt keeps what it writes.
 *
 * Inside a signed app bundle nothing is writable, so the environment file, the database and
 * everything under storage move to a folder of their own — `TAKT_DATA`, set by the app shell
 * before it starts the server. Without that variable the project folder is the data folder,
 * exactly as it always was.
 */
final class DataDirectory
{
    public const string ENV = 'TAKT_DATA';

    /** @var list<string> the folders Laravel expects under storage, created empty */
    private const array STORAGE = [
        'app/private',
        'app/public',
        'app/backups',
        'framework/cache/data',
        'framework/sessions',
        'framework/testing',
        'framework/views',
        'logs',
    ];

    public static function fromEnvironment(): ?self
    {
        $path = getenv(self::ENV);

        return is_string($path) && trim($path) !== '' ? new self(rtrim(trim($path), '/')) : null;
    }

    public function __construct(public readonly string $path) {}

    public function storage(): string
    {
        return $this->path.'/storage';
    }

    public function database(): string
    {
        return $this->path.'/database';
    }

    public function environmentFile(): string
    {
        return $this->path.'/.env';
    }

    /** The framework's own manifests — services, packages — which it would otherwise write into the bundle. */
    public function cache(): string
    {
        return $this->path.'/cache';
    }

    /** Creates every folder a run needs; a second call changes nothing. */
    public function prepare(): void
    {
        $folders = [$this->database(), $this->cache(), ...array_map(fn (string $sub): string => $this->storage().'/'.$sub, self::STORAGE)];

        foreach ($folders as $folder) {
            if (! is_dir($folder)) {
                mkdir($folder, 0o755, true);
            }
        }

        $database = $this->database().'/database.sqlite';

        if (! is_file($database)) {
            touch($database);
        }
    }
}
