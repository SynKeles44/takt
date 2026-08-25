<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\LocalUrl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Gives Takt a real name instead of localhost. Two things have to agree: /etc/hosts has to
 * point the name at this machine, and APP_URL has to carry it, so every link Takt writes
 * — the app window, the calendar feed, a reminder — uses that address.
 */
class HostnameCommand extends Command
{
    protected $signature = 'takt:hostname
                            {host? : The name Takt answers on, default local.takt.de}
                            {--port= : The port, default the one from APP_URL}
                            {--remove : Back to localhost}
                            {--dry-run : Show what would change}
                            {--hosts-file=/etc/hosts : Where the host entries live}
                            {--env-file= : Which .env to write, default the project one}';

    protected $description = 'Serve Takt under a name like local.takt.de instead of localhost';

    public function handle(): int
    {
        $hostsFile = (string) $this->option('hosts-file');
        $envFile = (string) ($this->option('env-file') ?: base_path('.env'));

        $remove = (bool) $this->option('remove');
        $host = $remove
            ? 'localhost'
            : (string) ($this->argument('host') ?: LocalUrl::host());

        if ($host === '' || preg_match('/^[a-z0-9.-]+$/i', $host) !== 1) {
            $this->components->error('That is not a host name: '.$host);

            return self::FAILURE;
        }

        $port = (int) ($this->option('port') ?: LocalUrl::port());
        $url = 'http://'.$host.($port === 80 ? '' : ':'.$port);

        if ($this->option('dry-run')) {
            $this->components->twoColumnDetail('Would set APP_URL', $url);
            $this->components->twoColumnDetail('Would '.($remove ? 'remove from' : 'write to').' '.$hostsFile, LocalUrl::hostsLine($host));

            return self::SUCCESS;
        }

        /*
         * The name has to reach this machine before APP_URL may carry it — otherwise every
         * link Takt writes, assets included, would point at an address nothing answers on.
         */
        $hosts = $remove || LocalUrl::isLoopbackName($host)
            ? $this->dropHosts($hostsFile)
            : $this->ensureHosts($hostsFile, $host);

        $reachable = LocalUrl::isLoopbackName($host) || $this->reaches($host, $hostsFile);

        // the port is wanted either way; only the name waits for the hosts entry
        $effective = $reachable ? $url : 'http://localhost'.($port === 80 ? '' : ':'.$port);

        $this->writeEnv($envFile, $effective);
        config(['app.url' => $effective]);

        $this->call('config:clear');

        // the app window carries the address in its bundle, so it has to be rewritten —
        // but only ours, and never from a test run, which has no business touching the machine
        if ($this->rebuildsBundle()) {
            $this->call('takt:app', ['--port' => $port, '--force' => true]);
        }

        $this->newLine();
        $this->components->twoColumnDetail('Hosts file', $hosts);
        $this->components->twoColumnDetail('Address', $effective);

        if (! $reachable) {
            $this->components->warn($host.' does not reach this machine yet — staying on localhost until it does.');
        }

        return self::SUCCESS;
    }

    private function bundle(): string
    {
        return (getenv('HOME') ?: '').'/Applications/'.config('app.name').'.app';
    }

    /** Only a bundle that belongs to this installation, and only outside of tests. */
    private function rebuildsBundle(): bool
    {
        if (PHP_OS_FAMILY !== 'Darwin' || app()->environment('testing')) {
            return false;
        }

        $plist = $this->bundle().'/Contents/Info.plist';

        if (! is_file($plist)) {
            return false;
        }

        return str_contains((string) file_get_contents($plist), '<string>'.base_path().'</string>');
    }

    /** Either the hosts file we were given says so, or the resolver does. */
    private function reaches(string $host, string $hostsFile): bool
    {
        if (is_file($hostsFile)) {
            $contents = (string) file_get_contents($hostsFile);

            if (preg_match('/^[^#\n]*\s'.preg_quote($host, '/').'(\s|$)/m', $contents) === 1) {
                return true;
            }
        }

        return gethostbyname($host) === LocalUrl::BIND;
    }

    private function writeEnv(string $file, string $url): void
    {
        if (! is_file($file)) {
            $this->components->warn('No .env at '.$file.' — skipped APP_URL.');

            return;
        }

        $contents = (string) file_get_contents($file);
        $line = 'APP_URL='.$url;

        $contents = preg_match('/^APP_URL=.*$/m', $contents) === 1
            ? (string) preg_replace('/^APP_URL=.*$/m', $line, $contents)
            : rtrim($contents, "\n")."\n".$line."\n";

        file_put_contents($file, $contents);
    }

    /** Adds the line if it is not there yet — with sudo only if the file needs it. */
    private function ensureHosts(string $file, string $host): string
    {
        if (! is_file($file)) {
            return 'not found: '.$file;
        }

        $contents = (string) file_get_contents($file);

        if (preg_match('/^[^#\n]*\s'.preg_quote($host, '/').'(\s|$)/m', $contents) === 1) {
            return $host.' already points here';
        }

        $line = LocalUrl::hostsLine($host);

        if (is_writable($file)) {
            file_put_contents($file, rtrim($contents, "\n")."\n".$line."\n");

            return 'added '.$host;
        }

        // -n so it never sits there waiting for a password nobody typed
        $result = Process::run(['sudo', '-n', 'sh', '-c', 'printf "%s\n" '.escapeshellarg($line).' >> '.escapeshellarg($file)]);

        if ($result->successful()) {
            return 'added '.$host.' (via sudo)';
        }

        $this->newLine();
        $this->components->warn('The hosts file needs administrator rights. This one line does everything:');
        $this->line('  sudo sh -c \'printf "%s\\n" "'.$line.'" >> '.$file.'\' && php artisan takt:hostname '.$host);

        return 'needs one manual line';
    }

    private function dropHosts(string $file): string
    {
        if (! is_file($file) || ! is_writable($file)) {
            return 'left untouched (needs administrator rights)';
        }

        $contents = (string) file_get_contents($file);
        $cleaned = (string) preg_replace('/^.*# takt$\n?/m', '', $contents);

        if ($cleaned === $contents) {
            return 'nothing of ours in there';
        }

        file_put_contents($file, $cleaned);

        return 'removed our line';
    }
}
