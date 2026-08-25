<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Registers the local server as a login item, so the app is always there when the
 * bundle is opened — no terminal, no manual start.
 */
class AutostartCommand extends Command
{
    protected $signature = 'takt:autostart
                            {--remove : Remove the login item}
                            {--dry-run : Print the launch agent instead of installing it}
                            {--port=8000}
                            {--force : Take over a login item that points at another installation}';

    protected $description = 'Start the local server automatically at login (macOS launchd)';

    public function handle(): int
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            $this->components->error('Autostart is macOS only.');

            return self::FAILURE;
        }

        $label = 'de.'.Str::slug((string) config('app.name')).'.server';
        $path = getenv('HOME').'/Library/LaunchAgents/'.$label.'.plist';

        if ($this->option('remove')) {
            Process::run(['/bin/launchctl', 'bootout', 'gui/'.getmyuid().'/'.$label]);

            if (is_file($path)) {
                unlink($path);
            }

            $this->components->info('Autostart removed. Use `make start` or the app bundle again.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->line($this->plist($label, (int) $this->option('port')));

            return self::SUCCESS;
        }

        if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0o755, true)) {
            $this->components->error('Cannot write to '.dirname($path));

            return self::FAILURE;
        }

        /*
         * Only one login item can hold the port. If the one that is there belongs to another
         * copy of Takt, taking it over silently would stop that copy's server for good.
         */
        if (is_file($path) && ! $this->option('force')) {
            $existing = (string) file_get_contents($path);

            if (! str_contains($existing, base_path()) && preg_match('#<string>(/[^<]*)/artisan</string>#', $existing) === 1) {
                $this->components->warn('A login item already points at another installation:');
                $this->line('  '.trim((string) (preg_match('#<key>WorkingDirectory</key><string>([^<]+)</string>#', $existing, $match) === 1 ? $match[1] : 'unknown')));
                $this->components->info('Kept it. Use --force to take it over.');

                return self::SUCCESS;
            }
        }

        file_put_contents($path, $this->plist($label, (int) $this->option('port')));

        // a manually started server would hold the port and the agent would respawn forever
        $this->stopManualServer();

        Process::run(['/bin/launchctl', 'bootout', 'gui/'.getmyuid().'/'.$label]);

        // launchd needs a moment after a bootout before it accepts the same label again
        $load = Process::run(['/bin/launchctl', 'bootstrap', 'gui/'.getmyuid(), $path]);

        if ($load->failed()) {
            usleep(1_500_000);
            $load = Process::run(['/bin/launchctl', 'bootstrap', 'gui/'.getmyuid(), $path]);
        }

        if ($load->failed()) {
            $this->components->error('launchctl refused the agent: '.trim($load->errorOutput()));

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Login item', $path);
        $this->components->info('The server now starts with your session and restarts if it stops.');

        return self::SUCCESS;
    }

    /** The Makefile and the app bundle both write this pid file. */
    private function stopManualServer(): void
    {
        $pidFile = storage_path('app/takt-serve.pid');

        if (! is_file($pidFile)) {
            return;
        }

        $pid = (int) explode(' ', (string) file_get_contents($pidFile))[0];

        if ($pid > 0) {
            Process::run(['/bin/kill', (string) $pid]);
            $this->components->twoColumnDetail('Stopped manual server', 'pid '.$pid);
        }

        unlink($pidFile);
    }

    private function plist(string $label, int $port): string
    {
        $root = base_path();
        $php = PHP_BINARY;

        return <<<PLIST
        <?xml version="1.0" encoding="UTF-8"?>
        <!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
        <plist version="1.0">
        <dict>
            <key>Label</key><string>{$label}</string>
            <key>ProgramArguments</key>
            <array>
                <string>{$php}</string>
                <string>{$root}/artisan</string>
                <string>serve</string>
                <string>--host=127.0.0.1</string>
                <string>--port={$port}</string>
            </array>
            <key>WorkingDirectory</key><string>{$root}</string>
            <key>RunAtLoad</key><true/>
            <key>KeepAlive</key><true/>
            <key>ThrottleInterval</key><integer>10</integer>
            <key>ProcessType</key><string>Background</string>
            <key>StandardOutPath</key><string>{$root}/storage/logs/serve.log</string>
            <key>StandardErrorPath</key><string>{$root}/storage/logs/serve.log</string>
        </dict>
        </plist>
        PLIST;
    }
}
