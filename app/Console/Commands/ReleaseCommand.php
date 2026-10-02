<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\AppBundle;
use App\Support\AppIcon;
use App\Support\BuildFreshness;
use App\Support\ReleaseStage;
use App\Support\ShellEnvironment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * The app for people who have nothing installed.
 *
 * `takt:app` wraps a checkout: it points at the project folder and the machine's PHP. This builds
 * the other kind — a bundle that carries its own PHP, its own copy of the code and its own
 * dependencies, writes to ~/Library/Application Support and asks the user for nothing. Double
 * click, create an account, done. The toolchain lives on the machine that BUILDS it: Xcode's
 * command line tools, Composer, Node.
 */
class ReleaseCommand extends Command
{
    protected $signature = 'takt:release
                            {--arch= : aarch64 or x86_64, default the arch of this machine}
                            {--php=8.5.8 : The static PHP release to bundle}
                            {--app-version= : The version shown in Finder, default git describe}
                            {--identity=- : The codesign identity, "-" for ad-hoc}
                            {--dmg : Also write a disk image next to the zip}
                            {--path=dist : Where the results are written}
                            {--port=8000 : The port the app serves on}
                            {--update-repo= : owner/name the app looks for new releases in, default the origin remote; "none" switches it off}';

    protected $description = 'Build a self-contained macOS app with its own PHP — nothing to install for the user';

    /** The static PHP builds this command knows, by arch, with the checksum of the download. */
    private const array PHP = [
        '8.5.8' => [
            'aarch64' => '5e5032e8244a2367b1e8a9c70ff6f793dee7433966c9291da85fadf2167cd55f',
            'x86_64' => 'd5a9a505ebce66c7f6b4f4e16629c36f385f2d360df915875722d67fe8bb2161',
        ],
    ];

    private const string PHP_URL = 'https://dl.static-php.dev/static-php-cli/bulk/php-%s-cli-macos-%s.tar.gz';

    public function handle(): int
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            $this->components->error('The app bundle is built on a Mac.');

            return self::FAILURE;
        }

        if (! AppIcon::supported()) {
            $this->components->error('The GD extension is required to render the app icon.');

            return self::FAILURE;
        }

        $name = (string) config('app.name');
        $arch = (string) ($this->option('arch') ?: (php_uname('m') === 'arm64' ? 'aarch64' : 'x86_64'));
        $phpVersion = (string) $this->option('php');
        $version = self::version((string) ($this->option('app-version') ?: $this->describe()));
        $port = (int) $this->option('port');
        $dist = rtrim(base_path((string) $this->option('path')), '/');
        $work = storage_path('app/release');
        $stage = $work.'/stage';
        $bundle = $work.'/'.$name.'.app';

        if (! in_array($arch, ['aarch64', 'x86_64'], true)) {
            $this->components->error('Unknown arch '.$arch.' — aarch64 or x86_64.');

            return self::FAILURE;
        }

        $this->components->info(sprintf('Building %s %s for %s', $name, $version, $arch));

        // 1. the frontend, built and current
        if (! $this->frontend()) {
            return self::FAILURE;
        }

        // 2. the code, from git, without development baggage, with production dependencies
        if (! $this->stage($stage)) {
            return self::FAILURE;
        }

        // 3. the runtime
        $php = $this->php($work, $phpVersion, $arch);

        if ($php === null) {
            return self::FAILURE;
        }

        // 4. the bundle
        if (! $this->bundle($bundle, $name, $version, $port, $stage, $php, $arch)) {
            return self::FAILURE;
        }

        if (! AppBundle::sign($bundle, (string) $this->option('identity'))) {
            $this->components->error('codesign failed.');

            return self::FAILURE;
        }

        // 5. the files to hand out
        File::ensureDirectoryExists($dist);

        $base = sprintf('%s/%s-%s-%s', $dist, $name, $version, $arch);
        $zip = $base.'.zip';

        File::delete($zip);
        Process::timeout(300)->run(['/usr/bin/ditto', '-c', '-k', '--sequesterRsrc', '--keepParent', $bundle, $zip]);

        $this->components->twoColumnDetail('App', $bundle);
        $this->components->twoColumnDetail('Zip', $zip.' ('.$this->size($zip).')');

        if ($this->option('dmg')) {
            $dmg = $base.'.dmg';
            File::delete($dmg);
            $result = Process::timeout(600)->run(['/usr/bin/hdiutil', 'create', '-volname', $name, '-srcfolder', $bundle, '-ov', '-format', 'UDZO', $dmg]);
            $this->components->twoColumnDetail('Disk image', $result->successful() ? $dmg.' ('.$this->size($dmg).')' : 'hdiutil failed: '.trim($result->errorOutput()));
        }

        $this->newLine();

        if ((string) $this->option('identity') === '-') {
            $this->components->warn('Signed ad-hoc: on another Mac the first start needs System Settings → Privacy & Security → Open Anyway. For a plain double click, sign with a Developer ID (--identity) and notarize.');
        } else {
            $this->components->info('Notarize with: xcrun notarytool submit '.basename($zip).' --keychain-profile <profile> --wait && xcrun stapler staple '.$name.'.app');
        }

        return self::SUCCESS;
    }

    private function frontend(): bool
    {
        if (! BuildFreshness::forApp()->stale()) {
            $this->components->task('Frontend build is current');

            return true;
        }

        $npm = ShellEnvironment::binary('npm');

        if ($npm === null) {
            $this->components->error('The frontend is not built and npm was not found — run npm run build first.');

            return false;
        }

        $result = Process::path(base_path())->timeout(300)->env(ShellEnvironment::variables())->run([$npm, 'run', 'build', '--silent']);

        if ($result->failed()) {
            $this->components->error('npm run build failed: '.trim($result->errorOutput()));

            return false;
        }

        $this->components->task('Frontend built');

        return true;
    }

    private function stage(string $stage): bool
    {
        File::deleteDirectory($stage);

        if (! ReleaseStage::export(base_path(), $stage)) {
            $this->components->error('git ls-files failed — the release is built from what git sees.');

            return false;
        }

        ReleaseStage::prune($stage);

        foreach (ReleaseStage::BRING_ALONG as $relative) {
            $source = base_path($relative);

            if (! is_dir($source)) {
                $this->components->error($relative.' is missing — build the frontend and run takt:icons first.');

                return false;
            }

            File::copyDirectory($source, $stage.'/'.$relative);
        }

        $composer = ShellEnvironment::binary('composer');

        if ($composer === null) {
            $this->components->error('composer was not found.');

            return false;
        }

        $install = Process::path($stage)
            ->timeout(600)
            ->env(ShellEnvironment::variables() + ['COMPOSER_NO_INTERACTION' => '1'])
            ->run([$composer, 'install', '--no-dev', '--optimize-autoloader', '--no-progress', '--quiet']);

        if ($install->failed()) {
            $this->components->error('composer install failed: '.trim($install->errorOutput()));

            return false;
        }

        $this->components->task('Code staged with production dependencies');

        return true;
    }

    /** The static PHP binary for the arch — downloaded once, verified against the pinned checksum. */
    private function php(string $work, string $version, string $arch): ?string
    {
        $expected = self::PHP[$version][$arch] ?? null;
        $archive = sprintf('%s/php-%s-%s.tar.gz', $work, $version, $arch);
        $binary = sprintf('%s/php-%s-%s', $work, $version, $arch);

        if (is_file($binary) && ($expected === null || hash_file('sha256', $archive) === $expected)) {
            $this->components->task('PHP '.$version.' for '.$arch.' already downloaded');

            return $binary;
        }

        File::ensureDirectoryExists($work);

        $url = sprintf(self::PHP_URL, $version, $arch);
        $download = Process::timeout(900)->run(['/usr/bin/curl', '-fsSL', '-o', $archive, $url]);

        if ($download->failed()) {
            $this->components->error('Download failed: '.$url);

            return null;
        }

        $actual = hash_file('sha256', $archive);

        if ($expected === null) {
            $this->components->warn(sprintf('No pinned checksum for PHP %s/%s — downloaded %s, sha256 %s. Add it to ReleaseCommand::PHP once verified.', $version, $arch, basename($archive), $actual));
        } elseif ($actual !== $expected) {
            unlink($archive);
            $this->components->error('The downloaded PHP does not match its pinned checksum — not using it.');

            return null;
        }

        $extract = Process::path($work)->run(['/usr/bin/tar', '-xzf', $archive, 'php']);

        if ($extract->failed() || ! is_file($work.'/php')) {
            $this->components->error('Could not unpack '.basename($archive));

            return null;
        }

        rename($work.'/php', $binary);
        chmod($binary, 0o755);

        $this->components->task('PHP '.$version.' for '.$arch.' downloaded and verified');

        return $binary;
    }

    private function bundle(string $bundle, string $name, string $version, int $port, string $stage, string $php, string $arch): bool
    {
        File::deleteDirectory($bundle);

        foreach (["$bundle/Contents/MacOS", "$bundle/Contents/Resources"] as $directory) {
            File::ensureDirectoryExists($directory);
        }

        $repository = $this->updateRepository();

        file_put_contents($bundle.'/Contents/Info.plist', AppBundle::plist($name, $version, array_filter([
            'TaktBundled' => true,
            'TaktPort' => $port,
            'TaktHost' => 'localhost',
            // where the app asks for a newer release; without it the app never checks
            'TaktUpdateRepo' => $repository,
        ], fn (mixed $value): bool => $value !== '')));

        $error = null;

        if (! AppBundle::compileShell($bundle.'/Contents/MacOS/'.$name, $error, $arch)) {
            $this->components->error('The window shell did not compile: '.$error);

            return false;
        }

        chmod($bundle.'/Contents/MacOS/'.$name, 0o755);

        AppBundle::writeIcon($bundle.'/Contents/Resources/AppIcon.icns');

        copy($php, $bundle.'/Contents/Resources/php');
        chmod($bundle.'/Contents/Resources/php', 0o755);

        // found through PHPRC, which the shell sets; a static build has no ini of its own
        file_put_contents($bundle.'/Contents/Resources/php.ini', implode(PHP_EOL, [
            'memory_limit = 512M',
            'opcache.enable = 1',
            'opcache.enable_cli = 1',
            'opcache.validate_timestamps = 0',
            'expose_php = Off',
            'variables_order = EGPCS',
        ]).PHP_EOL);

        File::copyDirectory($stage, $bundle.'/Contents/Resources/app');

        $this->components->task('Bundle assembled');

        return true;
    }

    /**
     * The version as Finder and the file names show it. Tags are written `v0.1.0`, but
     * CFBundleShortVersionString is plain numbers — the first release showed "v0.1.0" in Finder.
     * Only a `v` in front of a digit goes, so a name that merely starts with v is left alone.
     */
    public static function version(string $raw): string
    {
        $version = trim($raw);

        return preg_match('/^[vV]\d/', $version) === 1 ? substr($version, 1) : $version;
    }

    /** owner/name from --update-repo, or from a github.com origin remote; empty switches updates off. */
    private function updateRepository(): string
    {
        $option = trim((string) $this->option('update-repo'));

        if ($option === 'none') {
            return '';
        }

        if ($option !== '') {
            return self::repository($option);
        }

        $remote = Process::path(base_path())->run(['/usr/bin/git', 'remote', 'get-url', 'origin']);

        return $remote->successful() ? self::repository(trim($remote->output())) : '';
    }

    /** `owner/name` out of whatever names a GitHub repository: the pair itself, an https or an ssh remote. */
    public static function repository(string $value): string
    {
        $value = trim($value);

        if (preg_match('#^[\w.-]+/[\w.-]+$#', $value) === 1) {
            return preg_replace('/\.git$/', '', $value) ?? '';
        }

        return preg_match('#github\.com[:/]([\w.-]+)/([\w.-]+?)(?:\.git)?/?$#', $value, $match) === 1 ? $match[1].'/'.$match[2] : '';
    }

    private function describe(): string
    {
        $result = Process::path(base_path())->run(['/usr/bin/git', 'describe', '--tags', '--always']);

        return $result->successful() ? trim($result->output()) : '0.0.0';
    }

    private function size(string $file): string
    {
        return is_file($file) ? round(filesize($file) / 1_048_576).' MB' : '?';
    }
}
