<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\AppIcon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Wraps the local installation in a macOS app bundle: double-click, own Dock icon,
 * own window, no terminal. The bundle starts the server if it is not running and
 * opens the app in a chromeless window.
 */
class BuildAppCommand extends Command
{
    protected $signature = 'takt:app
                            {--path= : Where the bundle is written (default: ~/Applications)}
                            {--port=8000 : Port the app serves on}
                            {--force : Overwrite a bundle that points at another installation}';

    protected $description = 'Build the macOS app bundle for this installation';

    /** icns needs these sizes, @1x and @2x */
    private const array ICON_SIZES = [16, 32, 128, 256, 512];

    public function handle(): int
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            $this->components->error('The app bundle is macOS only — use the browser or install the web app instead.');

            return self::FAILURE;
        }

        if (! AppIcon::supported()) {
            $this->components->error('The GD extension is required to render the app icon.');

            return self::FAILURE;
        }

        $name = (string) config('app.name');
        $target = rtrim($this->option('path') ?: getenv('HOME').'/Applications', '/');
        $bundle = $target.'/'.$name.'.app';
        $port = (int) $this->option('port');

        $existing = $this->bundleRoot($bundle);

        if ($existing !== null && $existing !== base_path() && ! $this->option('force')) {
            $this->components->warn(sprintf('%s.app already points at %s — kept it.', $name, $existing));
            $this->components->twoColumnDetail('Overwrite it with', 'php artisan takt:app --force');
            $this->components->twoColumnDetail('Or write elsewhere with', 'php artisan takt:app --path=…');

            return self::SUCCESS;
        }

        $this->components->info(sprintf('Building %s.app for %s', $name, base_path()));

        foreach (["$bundle/Contents/MacOS", "$bundle/Contents/Resources"] as $directory) {
            if (! is_dir($directory) && ! mkdir($directory, 0o755, true)) {
                $this->components->error('Cannot write to '.$directory);

                return self::FAILURE;
            }
        }

        file_put_contents($bundle.'/Contents/Info.plist', $this->plist($name, $port));

        $native = $this->compileShell($bundle.'/Contents/MacOS/'.$name);

        if (! $native) {
            file_put_contents($bundle.'/Contents/MacOS/'.$name, $this->launcher($port));
        }

        chmod($bundle.'/Contents/MacOS/'.$name, 0o755);

        $this->icon($bundle.'/Contents/Resources/AppIcon.icns');

        // ad-hoc signature: no certificate needed, but the system treats it as a real app
        Process::run(['/usr/bin/codesign', '--force', '--sign', '-', '--timestamp=none', $bundle]);

        // let Finder pick up the new bundle straight away
        Process::run(['/usr/bin/touch', $bundle]);

        $this->components->twoColumnDetail('Bundle', $bundle);
        $this->components->twoColumnDetail('Window', $native ? 'native (Cocoa + WebKit)' : 'browser window (swiftc missing)');
        $this->components->twoColumnDetail('Serves on', 'http://localhost:'.$port);
        $this->components->info('Open it from Finder or the Launchpad. Keep it in the Dock for one-click access.');

        return self::SUCCESS;
    }

    /** Which installation an existing bundle launches, if any. */
    private function bundleRoot(string $bundle): ?string
    {
        $plist = $bundle.'/Contents/Info.plist';

        if (! is_file($plist)) {
            return null;
        }

        $result = Process::run(['/usr/bin/plutil', '-extract', 'TaktRoot', 'raw', '-o', '-', $plist]);

        return $result->successful() ? trim($result->output()) : null;
    }

    /**
     * The window is a real Cocoa app around a WKWebView — no browser involved.
     * Without Xcode's toolchain the bundle falls back to a browser window.
     */
    private function compileShell(string $binary): bool
    {
        $source = base_path('desktop/main.swift');

        if (! is_file($source) || ! is_file('/usr/bin/swiftc')) {
            return false;
        }

        $build = Process::timeout(180)->run([
            '/usr/bin/swiftc',
            '-swift-version', '5',
            '-O',
            '-o', $binary,
            $source,
            '-framework', 'Cocoa',
            '-framework', 'WebKit',
            '-framework', 'UserNotifications',
        ]);

        if ($build->failed()) {
            $this->components->warn('swiftc failed, falling back to a browser window: '.trim($build->errorOutput()));

            return false;
        }

        return true;
    }

    private function plist(string $name, int $port): string
    {
        $identifier = 'de.'.Str::slug($name).'.app';
        $root = base_path();
        $php = PHP_BINARY;

        return <<<PLIST
        <?xml version="1.0" encoding="UTF-8"?>
        <!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
        <plist version="1.0">
        <dict>
            <key>CFBundleName</key><string>{$name}</string>
            <key>CFBundleDisplayName</key><string>{$name}</string>
            <key>CFBundleIdentifier</key><string>{$identifier}</string>
            <key>CFBundleExecutable</key><string>{$name}</string>
            <key>CFBundleIconFile</key><string>AppIcon</string>
            <key>CFBundlePackageType</key><string>APPL</string>
            <key>CFBundleShortVersionString</key><string>1.0</string>
            <key>CFBundleVersion</key><string>1</string>
            <key>LSMinimumSystemVersion</key><string>12.0</string>
            <key>LSUIElement</key><false/>
            <key>NSHighResolutionCapable</key><true/>
            <key>TaktPort</key><integer>{$port}</integer>
            <key>TaktRoot</key><string>{$root}</string>
            <key>TaktPhp</key><string>{$php}</string>
            <key>NSAppTransportSecurity</key>
            <dict>
                <key>NSAllowsLocalNetworking</key><true/>
            </dict>
        </dict>
        </plist>
        PLIST;
    }

    private function launcher(int $port): string
    {
        $root = base_path();
        $php = PHP_BINARY;

        return <<<SHELL
        #!/bin/bash
        # Generated by `php artisan takt:app` — regenerate after moving the project.
        set -u

        ROOT="{$root}"
        PHP="{$php}"
        PORT="{$port}"
        URL="http://localhost:\$PORT"
        LOG="\$ROOT/storage/logs/serve.log"
        PID="\$ROOT/storage/app/takt-serve.pid"

        note() { /usr/bin/logger -t takt "\$1"; }

        running() { /usr/bin/curl -s -o /dev/null -m 1 "\$URL"; }

        if [ "\${1:-}" = "--dry-run" ]; then
            running && echo "server up" || echo "server down"
            exit 0
        fi

        START_ONLY=0
        [ "\${1:-}" = "--start-only" ] && START_ONLY=1

        AGENT="\$HOME/Library/LaunchAgents/de.takt.server.plist"

        if ! running; then
            if [ -f "\$AGENT" ]; then
                # the login item owns the server; just make sure it is up
                note "kickstarting the login item"
                /bin/launchctl kickstart "gui/\$(id -u)/de.takt.server" >/dev/null 2>&1
            else
                note "starting server on \$PORT"
                cd "\$ROOT" || exit 1
                "\$PHP" artisan serve --port="\$PORT" >>"\$LOG" 2>&1 &
                echo "\$! \$PORT" > "\$PID"
            fi

            for _ in \$(seq 1 40); do
                running && break
                sleep 0.25
            done
        fi

        if ! running; then
            if [ "\$START_ONLY" = "1" ]; then
                echo "server failed to start" >&2
                exit 1
            fi

            /usr/bin/osascript -e 'display alert "Takt" message "Der lokale Server ist nicht gestartet. Details in storage/logs/serve.log."'
            exit 1
        fi

        if [ "\$START_ONLY" = "1" ]; then
            echo "server up"
            exit 0
        fi

        # a chromeless window keeps it feeling like an app; otherwise the default browser
        for BROWSER in "/Applications/Google Chrome.app" "/Applications/Brave Browser.app" "/Applications/Microsoft Edge.app" "/Applications/Chromium.app"; do
            if [ -d "\$BROWSER" ]; then
                exec /usr/bin/open -na "\$BROWSER" --args --app="\$URL" --user-data-dir="\$HOME/Library/Application Support/Takt/window"
            fi
        done

        exec /usr/bin/open "\$URL"
        SHELL;
    }

    private function icon(string $path): void
    {
        $iconset = sys_get_temp_dir().'/takt-'.Str::random(8).'.iconset';

        mkdir($iconset, 0o755, true);

        foreach (self::ICON_SIZES as $size) {
            AppIcon::write($size, sprintf('%s/icon_%dx%d.png', $iconset, $size, $size));
            AppIcon::write($size * 2, sprintf('%s/icon_%dx%d@2x.png', $iconset, $size, $size));
        }

        $result = Process::run(['/usr/bin/iconutil', '-c', 'icns', $iconset, '-o', $path]);

        if ($result->failed()) {
            $this->components->warn('iconutil failed, falling back to a single PNG icon.');

            AppIcon::write(1024, str_replace('.icns', '.png', $path));
        }

        Process::run(['/bin/rm', '-rf', $iconset]);
    }
}
