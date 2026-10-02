<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * The pieces two bundles share: the one `takt:app` writes for a checkout, and the self-contained
 * one `takt:release` ships. Shell compilation, the icon and the property list are the same work
 * either way; only the keys in the list say which kind of bundle it is.
 */
final class AppBundle
{
    /** icns needs these sizes, @1x and @2x */
    private const array ICON_SIZES = [16, 32, 128, 256, 512];

    /**
     * The window is a real Cocoa app around a WKWebView — no browser involved. Without Xcode's
     * toolchain there is nothing to compile; the caller decides what to do then.
     */
    public static function compileShell(string $binary, ?string &$error = null, ?string $arch = null): bool
    {
        $source = base_path('desktop/main.swift');

        if (! is_file($source) || ! is_file('/usr/bin/swiftc')) {
            $error = 'swiftc is not installed (xcode-select --install)';

            return false;
        }

        $arguments = [
            '/usr/bin/swiftc',
            '-swift-version', '5',
            '-O',
            '-o', $binary,
            $source,
            '-framework', 'Cocoa',
            '-framework', 'WebKit',
            '-framework', 'UserNotifications',
        ];

        // a release for the other kind of Mac is cross-compiled; a checkout bundle builds for this one
        if ($arch !== null) {
            array_push($arguments, '-target', ($arch === 'x86_64' ? 'x86_64' : 'arm64').'-apple-macos12');
        }

        $build = Process::timeout(180)->run($arguments);

        if ($build->failed()) {
            $error = trim($build->errorOutput());

            return false;
        }

        return true;
    }

    /** Renders the icon set and folds it into one icns; a single PNG when iconutil is missing. */
    public static function writeIcon(string $path): bool
    {
        $iconset = sys_get_temp_dir().'/takt-'.Str::random(8).'.iconset';

        mkdir($iconset, 0o755, true);

        foreach (self::ICON_SIZES as $size) {
            AppIcon::write($size, sprintf('%s/icon_%dx%d.png', $iconset, $size, $size));
            AppIcon::write($size * 2, sprintf('%s/icon_%dx%d@2x.png', $iconset, $size, $size));
        }

        $result = Process::run(['/usr/bin/iconutil', '-c', 'icns', $iconset, '-o', $path]);

        if ($result->failed()) {
            AppIcon::write(1024, str_replace('.icns', '.png', $path));
        }

        Process::run(['/bin/rm', '-rf', $iconset]);

        return $result->successful();
    }

    /**
     * The property list. Everything the shell reads at runtime is a `Takt…` key, so a bundle can
     * be re-pointed without recompiling; the two bundle kinds differ only in which of them exist.
     *
     * @param  array<string, string|int|bool>  $takt  the Takt-prefixed keys and their values
     */
    public static function plist(string $name, string $version, array $takt): string
    {
        $identifier = 'de.'.Str::slug($name).'.app';
        $escaped = htmlspecialchars($name, ENT_XML1);
        $shortVersion = htmlspecialchars($version, ENT_XML1);

        $keys = '';

        foreach ($takt as $key => $value) {
            $keys .= match (true) {
                is_bool($value) => sprintf("    <key>%s</key><%s/>\n", $key, $value ? 'true' : 'false'),
                is_int($value) => sprintf("    <key>%s</key><integer>%d</integer>\n", $key, $value),
                default => sprintf("    <key>%s</key><string>%s</string>\n", $key, htmlspecialchars($value, ENT_XML1)),
            };
        }

        return <<<PLIST
        <?xml version="1.0" encoding="UTF-8"?>
        <!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
        <plist version="1.0">
        <dict>
            <key>CFBundleName</key><string>{$escaped}</string>
            <key>CFBundleDisplayName</key><string>{$escaped}</string>
            <key>CFBundleIdentifier</key><string>{$identifier}</string>
            <key>CFBundleExecutable</key><string>{$escaped}</string>
            <key>CFBundleIconFile</key><string>AppIcon</string>
            <key>CFBundlePackageType</key><string>APPL</string>
            <key>CFBundleShortVersionString</key><string>{$shortVersion}</string>
            <key>CFBundleVersion</key><string>{$shortVersion}</string>
            <key>LSMinimumSystemVersion</key><string>12.0</string>
            <key>LSUIElement</key><false/>
            <key>NSHighResolutionCapable</key><true/>
        {$keys}    <key>NSCalendarsUsageDescription</key><string>Takt zeigt Deine Termine als Buchungsvorschläge — sie bleiben auf diesem Rechner.</string>
            <key>NSCalendarsFullAccessUsageDescription</key><string>Takt zeigt Deine Termine als Buchungsvorschläge — sie bleiben auf diesem Rechner.</string>
            <key>NSAppTransportSecurity</key>
            <dict>
                <key>NSAllowsLocalNetworking</key><true/>
            </dict>
        </dict>
        </plist>
        PLIST;
    }

    /** Ad-hoc by default: no certificate needed, and the system still treats it as a real app. */
    public static function sign(string $bundle, string $identity = '-'): bool
    {
        $arguments = ['/usr/bin/codesign', '--force', '--deep', '--sign', $identity];

        if ($identity === '-') {
            $arguments[] = '--timestamp=none';
        } else {
            // a real identity gets the hardened runtime and a trusted timestamp, which notarization requires
            array_push($arguments, '--options', 'runtime', '--timestamp');
        }

        $arguments[] = $bundle;

        return Process::timeout(300)->run($arguments)->successful();
    }
}
