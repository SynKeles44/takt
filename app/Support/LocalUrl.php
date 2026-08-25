<?php

declare(strict_types=1);

namespace App\Support;

/**
 * One source for the address Takt answers on. The server always binds to the loopback;
 * the host name is what the user sees, and it resolves to the loopback through /etc/hosts.
 */
final class LocalUrl
{
    public const string DEFAULT_HOST = 'local.takt.de';

    public const int DEFAULT_PORT = 8000;

    public const string BIND = '127.0.0.1';

    public static function host(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : self::DEFAULT_HOST;
    }

    public static function port(): int
    {
        $port = parse_url((string) config('app.url'), PHP_URL_PORT);

        return is_int($port) && $port > 0 ? $port : self::DEFAULT_PORT;
    }

    public static function url(?int $port = null): string
    {
        $port ??= self::port();

        return 'http://'.self::host().($port === 80 ? '' : ':'.$port);
    }

    /** The line /etc/hosts needs for the name to reach this machine. */
    public static function hostsLine(?string $host = null): string
    {
        return self::BIND.' '.($host ?? self::host()).' # takt';
    }

    /** A name that needs no hosts entry at all. */
    public static function isLoopbackName(string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }
}
