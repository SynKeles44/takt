<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\ReleaseCommand;
use App\Support\AppBundle;
use App\Support\ReleaseStage;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The self-contained app carries the code and its dependencies, never the development tree.
 */
class ReleaseStageTest extends TestCase
{
    private string $stage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stage = storage_path('framework/testing/stage-'.uniqid());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->stage);

        parent::tearDown();
    }

    public function test_everything_it_leaves_behind_is_a_tracked_path(): void
    {
        $tracked = explode("\n", trim((string) shell_exec('cd '.escapeshellarg(base_path()).' && git ls-files')));

        foreach (ReleaseStage::LEAVE_BEHIND as $relative) {
            $hit = array_filter($tracked, fn (string $file): bool => $file === $relative || str_starts_with($file, $relative.'/'));

            $this->assertNotEmpty($hit, $relative.' is on the leave-behind list but nothing tracked lives there — the list has rotted');
        }
    }

    public function test_the_shipped_tree_keeps_what_the_app_needs_and_drops_development(): void
    {
        foreach (['app', 'bootstrap', 'config', 'database/migrations', 'lang', 'public', 'resources/views', 'routes', 'storage/app', 'tests/Feature', 'resources/css', 'resources/js', 'desktop', 'docker', 'docs', '.github/workflows'] as $folder) {
            File::ensureDirectoryExists($this->stage.'/'.$folder);
            touch($this->stage.'/'.$folder.'/keep');
        }

        foreach (['artisan', 'composer.json', 'composer.lock', '.env.example', 'Makefile', 'README.md', 'LICENSE', 'Dockerfile', 'compose.yaml', 'install.sh', 'update.sh', 'package.json', 'package-lock.json', 'vite.config.js', 'phpunit.xml', '.gitignore'] as $file) {
            touch($this->stage.'/'.$file);
        }

        ReleaseStage::prune($this->stage);

        foreach (['app', 'bootstrap', 'config', 'database/migrations', 'lang', 'public', 'resources/views', 'routes', 'storage/app'] as $kept) {
            $this->assertDirectoryExists($this->stage.'/'.$kept);
        }

        foreach (['artisan', 'composer.json', 'composer.lock', '.env.example', 'Makefile', 'README.md', 'LICENSE'] as $kept) {
            $this->assertFileExists($this->stage.'/'.$kept);
        }

        foreach (['tests', 'resources/css', 'resources/js', 'desktop', 'docker', 'docs', '.github'] as $gone) {
            $this->assertDirectoryDoesNotExist($this->stage.'/'.$gone);
        }

        foreach (['Dockerfile', 'compose.yaml', 'install.sh', 'update.sh', 'package.json', 'package-lock.json', 'vite.config.js', 'phpunit.xml', '.gitignore'] as $gone) {
            $this->assertFileDoesNotExist($this->stage.'/'.$gone);
        }
    }

    public function test_the_bundled_property_list_marks_itself_and_names_no_checkout(): void
    {
        $plist = AppBundle::plist('Takt', '1.2.3', ['TaktBundled' => true, 'TaktPort' => 8000, 'TaktHost' => 'localhost']);

        $this->assertStringContainsString('<key>TaktBundled</key><true/>', $plist);
        $this->assertStringContainsString('<key>TaktPort</key><integer>8000</integer>', $plist);
        $this->assertStringContainsString('<key>CFBundleShortVersionString</key><string>1.2.3</string>', $plist);
        $this->assertStringContainsString('<key>CFBundleIdentifier</key><string>de.takt.app</string>', $plist);
        $this->assertStringNotContainsString('TaktRoot', $plist);
        $this->assertStringNotContainsString('TaktPhp', $plist);
    }

    /** A tag is `v0.1.0`; Finder and the file names want `0.1.0`. */
    public function test_the_version_drops_the_v_of_a_tag_and_nothing_else(): void
    {
        $this->assertSame('0.1.0', ReleaseCommand::version('v0.1.0'));
        $this->assertSame('0.1.0', ReleaseCommand::version(" V0.1.0\n"));
        $this->assertSame('0.2.0-3-gab2c8c7', ReleaseCommand::version('v0.2.0-3-gab2c8c7'));
        $this->assertSame('0.1.0', ReleaseCommand::version('0.1.0'));
        $this->assertSame('ab2c8c7', ReleaseCommand::version('ab2c8c7'));
        $this->assertSame('vnext', ReleaseCommand::version('vnext'));
    }

    public function test_the_checkout_property_list_points_at_the_installation(): void
    {
        $plist = AppBundle::plist('Takt', '1.0', ['TaktPort' => 8000, 'TaktHost' => 'local.takt.de', 'TaktRoot' => '/Users/me/Takt', 'TaktPhp' => '/opt/homebrew/bin/php']);

        $this->assertStringContainsString('<key>TaktRoot</key><string>/Users/me/Takt</string>', $plist);
        $this->assertStringContainsString('<key>TaktPhp</key><string>/opt/homebrew/bin/php</string>', $plist);
        $this->assertStringNotContainsString('TaktBundled', $plist);
    }
}
