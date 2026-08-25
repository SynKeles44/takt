<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

class SetupCommandTest extends TestCase
{
    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();

        // never the machine's own files: the setup writes a hosts entry and an APP_URL
        $this->folder = storage_path('framework/testing/setup-'.uniqid());
        File::makeDirectory($this->folder, recursive: true);
        File::put($this->folder.'/hosts', "127.0.0.1\tlocalhost\n");
        File::put($this->folder.'/.env', "APP_NAME=Takt\nAPP_URL=http://localhost:8000\n");
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->folder);

        parent::tearDown();
    }

    /** @param  array<string, mixed>  $options */
    private function runSetup(array $options = []): PendingCommand
    {
        return $this->artisan('takt:setup', array_merge([
            '--no-app' => true,
            '--no-autostart' => true,
            '--hosts-file' => $this->folder.'/hosts',
            '--env-file' => $this->folder.'/.env',
        ], $options));
    }

    public function test_it_walks_through_every_step_and_names_the_address(): void
    {
        // no app bundle, no login item, no server — those touch the machine, not the project
        $this->runSetup(['--host' => 'local.takt.de', '--port' => 8000])
            ->expectsOutputToContain('Setting Takt up.')
            ->expectsOutputToContain('local.takt.de')
            ->assertExitCode(0);

        $this->assertStringContainsString('local.takt.de', File::get($this->folder.'/hosts'));
        $this->assertStringContainsString('APP_URL=http://local.takt.de:8000', File::get($this->folder.'/.env'));
    }

    public function test_the_icon_it_needs_for_notifications_exists_afterwards(): void
    {
        $this->runSetup()->assertExitCode(0);

        $this->assertTrue(File::exists(public_path('icons/icon-192.png')));
    }

    public function test_the_install_script_gets_by_with_one_command(): void
    {
        $script = File::get(base_path('install.sh'));

        $this->assertStringContainsString('takt:setup', $script);

        // nothing is left for the user to run afterwards
        foreach (['takt:app', 'takt:autostart', 'artisan migrate', 'key:generate'] as $gone) {
            $this->assertStringNotContainsString($gone, $script);
        }
    }

    public function test_the_update_script_runs_the_same_setup(): void
    {
        $this->assertStringContainsString('takt:setup', File::get(base_path('update.sh')));
    }
}
