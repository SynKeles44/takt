<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\DataDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PrepareCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/prepare-'.uniqid());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        putenv(DataDirectory::ENV);

        parent::tearDown();
    }

    public function test_it_refuses_outside_the_bundle(): void
    {
        putenv(DataDirectory::ENV);

        $this->artisan('takt:prepare')->assertFailed();
    }

    public function test_the_first_run_writes_the_environment_with_a_key_and_the_address(): void
    {
        putenv(DataDirectory::ENV.'='.$this->root);

        $this->artisan('takt:prepare', ['--port' => 8123, '--name' => 'Takt'])->assertSuccessful();

        $env = (string) file_get_contents($this->root.'/.env');

        $this->assertMatchesRegularExpression('/^APP_KEY=base64:[A-Za-z0-9+\/=]{40,}$/m', $env);
        $this->assertStringContainsString('APP_URL=http://localhost:8123', $env);
        $this->assertStringContainsString('APP_NAME=Takt', $env);
        $this->assertStringContainsString('APP_ENV=production', $env);
        $this->assertStringContainsString('DB_CONNECTION=sqlite', $env);
        $this->assertFileExists($this->root.'/database/database.sqlite');
        $this->assertDirectoryExists($this->root.'/storage/logs');
    }

    /** The first launch has no environment file, so the framework's default name must not be the one written. */
    public function test_without_a_name_it_writes_takt_and_never_the_framework_default(): void
    {
        putenv(DataDirectory::ENV.'='.$this->root);
        config(['app.name' => 'Laravel']);

        $this->artisan('takt:prepare')->assertSuccessful();

        $this->assertStringContainsString('APP_NAME=Takt', (string) file_get_contents($this->root.'/.env'));
    }

    public function test_a_second_run_keeps_the_key(): void
    {
        putenv(DataDirectory::ENV.'='.$this->root);

        $this->artisan('takt:prepare')->assertSuccessful();
        $first = (string) file_get_contents($this->root.'/.env');

        $this->artisan('takt:prepare', ['--port' => 9000])->assertSuccessful();

        // the key — and with it every encrypted token — survives; the file is not rewritten
        $this->assertSame($first, (string) file_get_contents($this->root.'/.env'));
    }
}
