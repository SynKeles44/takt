<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ProjectScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProjectScanTest extends TestCase
{
    use RefreshDatabase;

    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->folder = storage_path('framework/testing/scan-'.uniqid());
        File::makeDirectory($this->folder, recursive: true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->folder);

        parent::tearDown();
    }

    public function test_a_makefile_with_a_start_target_wins(): void
    {
        File::put($this->folder.'/Makefile', "PORT ?= 8123\n\nstart:\n\tphp -S localhost:8123\n");

        $result = app(ProjectScanner::class)->scan($this->folder);

        $this->assertTrue($result['found']);
        $this->assertSame(basename($this->folder), $result['name']);
        $this->assertSame('make start', $result['start_command']);
        $this->assertSame(8123, $result['port']);
        $this->assertFalse($result['git']);
        $this->assertNull($result['repository']);
    }

    public function test_a_node_project_without_a_makefile_uses_its_own_script(): void
    {
        File::put($this->folder.'/package.json', json_encode(['scripts' => ['dev' => 'vite']]));

        $result = app(ProjectScanner::class)->scan($this->folder);

        $this->assertSame('npm run dev', $result['start_command']);
        $this->assertSame(5173, $result['port']);
    }

    public function test_an_empty_folder_falls_back_to_make_start_without_a_port(): void
    {
        $result = app(ProjectScanner::class)->scan($this->folder);

        $this->assertSame('make start', $result['start_command']);
        $this->assertNull($result['port']);
    }

    public function test_a_folder_that_is_not_there_reports_nothing(): void
    {
        $this->assertSame(['found' => false], app(ProjectScanner::class)->scan($this->folder.'/weg'));
    }

    public function test_the_endpoint_answers_the_form(): void
    {
        $this->login();
        File::put($this->folder.'/artisan', '');

        $this->postJson(route('projects.scan'), ['path' => $this->folder])
            ->assertOk()
            ->assertJson([
                'found' => true,
                'name' => basename($this->folder),
                'start_command' => 'php artisan serve',
                'port' => 8000,
            ]);
    }

    public function test_the_endpoint_needs_a_login(): void
    {
        $this->postJson(route('projects.scan'), ['path' => $this->folder])->assertUnauthorized();
    }

    public function test_the_form_offers_the_picker_and_make_start(): void
    {
        $this->login();

        $this->get(route('projects'))
            ->assertOk()
            ->assertSee('data-pick-folder', false)
            ->assertSee('value="make start"', false)
            ->assertSee(__('app.dev.port_hint'));
    }
}
