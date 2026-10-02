<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\FrontendBuild;
use App\Support\BuildFreshness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class BuildFreshnessTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/freshness-'.uniqid());
        File::makeDirectory($this->root.'/src', recursive: true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_a_missing_manifest_is_stale(): void
    {
        $this->assertTrue((new BuildFreshness($this->root.'/manifest.json', [$this->root.'/src']))->stale());
    }

    public function test_a_manifest_newer_than_every_source_is_fresh(): void
    {
        File::put($this->root.'/src/app.css', 'a');
        touch($this->root.'/src/app.css', time() - 120);
        File::put($this->root.'/manifest.json', '{}');

        $this->assertFalse((new BuildFreshness($this->root.'/manifest.json', [$this->root.'/src']))->stale());
    }

    public function test_a_source_edited_after_the_build_makes_it_stale(): void
    {
        File::put($this->root.'/manifest.json', '{}');
        touch($this->root.'/manifest.json', time() - 120);
        File::makeDirectory($this->root.'/src/deep');
        File::put($this->root.'/src/deep/app.js', 'b');

        $this->assertTrue((new BuildFreshness($this->root.'/manifest.json', [$this->root.'/src']))->stale());
    }

    public function test_a_single_file_counts_as_a_source_too(): void
    {
        File::put($this->root.'/manifest.json', '{}');
        touch($this->root.'/manifest.json', time() - 120);
        File::put($this->root.'/vite.config.js', 'c');

        $this->assertTrue((new BuildFreshness($this->root.'/manifest.json', [$this->root.'/vite.config.js']))->stale());
    }

    public function test_the_page_warns_when_the_build_is_behind(): void
    {
        $this->login();

        $this->app->bind(BuildFreshness::class, fn () => new BuildFreshness($this->root.'/missing.json', []));

        $this->get(route('settings'))->assertOk()->assertSee('data-stale-build', escape: false)->assertSee(route('build'), escape: false);
    }

    public function test_the_button_runs_the_build_and_asks_for_a_reload(): void
    {
        $this->login();
        Process::fake();
        $this->app->bind(FrontendBuild::class, fn () => new FrontendBuild('/usr/local/bin/npm', base_path()));

        $this->post(route('build'))->assertOk()->assertJson(['reload' => true]);

        Process::assertRan(fn ($process): bool => $process->command === ['/usr/local/bin/npm', 'run', 'build', '--silent'] && $process->path === base_path());
    }

    public function test_a_failed_build_reports_its_last_lines_instead_of_reloading(): void
    {
        $this->login();
        Process::fake(['*' => Process::result(output: '', errorOutput: "vite v8\nerror during build:\napp.css:12 unexpected token", exitCode: 1)]);
        $this->app->bind(FrontendBuild::class, fn () => new FrontendBuild('/usr/local/bin/npm', base_path()));

        $this->post(route('build'))
            ->assertOk()
            ->assertJsonMissing(['reload' => true])
            ->assertJsonFragment(['status' => __('app.build.failed', ['reason' => 'vite v8 error during build: app.css:12 unexpected token'])]);
    }

    public function test_without_npm_the_button_says_so_and_runs_nothing(): void
    {
        $this->login();
        Process::fake();
        $this->app->bind(FrontendBuild::class, fn () => new FrontendBuild(null, base_path()));

        $this->post(route('build'))->assertOk()->assertJsonFragment(['status' => __('app.build.failed', ['reason' => __('app.build.no_npm')])]);

        Process::assertNothingRan();
    }

    public function test_the_build_needs_a_signed_in_user(): void
    {
        $this->post(route('build'))->assertRedirect(route('login'));
    }

    public function test_the_page_stays_quiet_when_the_build_is_current(): void
    {
        $this->login();

        File::put($this->root.'/manifest.json', '{}');

        $this->app->bind(BuildFreshness::class, fn () => new BuildFreshness($this->root.'/manifest.json', []));

        $this->get(route('settings'))->assertOk()->assertDontSee('data-stale-build', escape: false);
    }
}
