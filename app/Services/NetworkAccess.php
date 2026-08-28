<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\ShellEnvironment;
use Illuminate\Support\Facades\Process;

/**
 * Whether Takt answers on the local network instead of the loopback only. The switch is a file
 * rather than a setting in the database, because the app shell has to read it before it starts
 * the server — and the shell does not speak to the database.
 *
 * This is plain HTTP inside your own network. The login stays mandatory, and the switch says so.
 */
final class NetworkAccess
{
    private const string MARKER = 'network-access';

    public function enabled(): bool
    {
        return is_file($this->path());
    }

    public function enable(): void
    {
        @mkdir(dirname($this->path()), 0o755, true);

        file_put_contents($this->path(), "on\n");
    }

    public function disable(): void
    {
        @unlink($this->path());
    }

    public function path(): string
    {
        return storage_path('app/'.self::MARKER);
    }

    /** The address a phone in the same network can open, or null when there is no network. */
    public function address(int $port = 8000): ?string
    {
        $ip = $this->ip();

        return $ip === null ? null : 'http://'.$ip.($port === 80 ? '' : ':'.$port);
    }

    public function ip(): ?string
    {
        foreach (['en0', 'en1'] as $interface) {
            $result = Process::env(ShellEnvironment::variables())
                ->timeout(5)
                ->run(['ipconfig', 'getifaddr', $interface]);

            $ip = trim($result->output());

            if ($result->successful() && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                return $ip;
            }
        }

        return null;
    }
}
