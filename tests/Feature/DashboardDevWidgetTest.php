<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RunStatus;
use App\Enums\Widget;
use App\Models\CommandRun;
use App\Models\DashboardWidget;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Dashboard;
use App\Services\Releases;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The five development tiles. Each one must answer from something cheap — the local board, the
 * manifests on disk, a cache — because a dashboard that shells out per tile is the slow dashboard
 * these widgets were added to avoid.
 */
class DashboardDevWidgetTest extends TestCase
{
    use RefreshDatabase;

    private const string CONTAINERS = "abc123\x1ftakt-web-1\x1fnginx\x1frunning\x1fUp 2 days\x1f\x1ftakt\x1fweb\x1f2 days ago
def456\x1ftakt-db-1\x1fmariadb\x1fexited\x1fExited (0)\x1f\x1ftakt\x1fdb\x1f2 days ago";

    protected function setUp(): void
    {
        parent::setUp();

        // No tile may reach a real daemon, a real git, or a real package manager.
        Process::fake(["*'ps'*" => Process::result(output: self::CONTAINERS), '*' => Process::result(output: '')]);
        Process::preventStrayProcesses();

        $this->login();
    }

    private function show(Widget $widget): TestResponse
    {
        DashboardWidget::query()->create(['widget' => $widget, 'span' => 4, 'rows' => 4]);

        $this->user()->forceFill(['dashboard_arranged' => true])->save();

        return $this->get(route('dashboard'))->assertOk();
    }

    private function user(): User
    {
        return auth()->user();
    }

    /**
     * The ticket tile reads the cached Linear issues, because the board's columns are Linear's
     * workflow now. It used to count local columns, and a tile still saying "today, next, waiting"
     * would be describing a board nobody can see.
     */
    public function test_the_ticket_tile_counts_what_is_open_and_names_what_is_next(): void
    {
        Http::fake(['api.linear.app/graphql' => Http::response(['data' => ['viewer' => ['assignedIssues' => ['nodes' => [
            [
                'identifier' => 'COR-1', 'title' => 'Dringend', 'url' => '', 'updatedAt' => '2026-09-30T08:00:00.000Z',
                'priority' => 1, 'state' => ['name' => 'In Progress', 'type' => 'started'],
                'team' => ['key' => 'COR', 'name' => 'Core'],
            ],
            [
                'identifier' => 'COR-2', 'title' => 'Schon fertig', 'url' => '', 'updatedAt' => '2026-09-29T08:00:00.000Z',
                'state' => ['name' => 'Done', 'type' => 'completed'], 'team' => ['key' => 'COR', 'name' => 'Core'],
            ],
        ]]]]])]);

        $this->user()->forceFill(['linear_token' => 'lin_api_test'])->save();

        $this->show(Widget::Tickets)
            ->assertSee('Dringend')
            ->assertSee('COR-1')
            // a finished ticket is not open, and the tile is about what is left
            ->assertDontSee('Schon fertig');

        $data = app(Dashboard::class)->data(Widget::Tickets, $this->user());

        $this->assertSame(1, $data['open']);
        $this->assertSame(1, $data['started']);
    }

    public function test_an_account_without_linear_sees_an_empty_ticket_tile(): void
    {
        Http::fake();

        $this->show(Widget::Tickets)->assertSee(__('app.widget.tickets.empty'));

        Http::assertNothingSent();
    }

    public function test_the_package_tile_counts_every_declared_dependency(): void
    {
        $path = sys_get_temp_dir().'/takt-widget-'.bin2hex(random_bytes(4));
        mkdir($path, 0o755, true);
        file_put_contents($path.'/composer.json', json_encode(['require' => ['laravel/framework' => '^13.0']]));
        file_put_contents($path.'/package.json', json_encode(['devDependencies' => ['vite' => '^8.0.0']]));

        Project::query()->create(['name' => 'Testprojekt', 'path' => $path]);

        $data = app(Dashboard::class)->data(Widget::Packages, $this->user());

        $this->assertSame(2, $data['summary']['total']);
        $this->assertSame([], $data['advisories']);

        $this->show(Widget::Packages)->assertSee(__('app.widget.packages.unchecked', ['count' => 1]));

        array_map('unlink', glob($path.'/*') ?: []);
        rmdir($path);
    }

    public function test_the_docker_tile_reads_the_daemon_once_and_then_the_cache(): void
    {
        $this->show(Widget::Docker)
            ->assertSee('takt')
            ->assertSee(__('app.widget.docker.running'));

        $this->get(route('dashboard'))->assertOk();

        Process::assertRanTimes(fn ($process): bool => in_array('ps', (array) $process->command, true), 1);
    }

    public function test_the_release_tile_reads_the_cache_and_never_git(): void
    {
        $project = Project::query()->create(['name' => 'Testprojekt', 'path' => sys_get_temp_dir()]);

        Cache::put('releases.'.$this->user()->getKey(), [
            $project->getKey() => [[
                'tag' => 'v4.2.0',
                'at' => Carbon::now()->subDay()->toIso8601String(),
                'subject' => 'Release',
            ]],
        ], Releases::CACHE_SECONDS);

        $this->show(Widget::Releases)->assertSee('v4.2.0');

        Process::assertNothingRan();
    }

    public function test_a_cold_release_cache_says_so_rather_than_showing_nothing(): void
    {
        $this->show(Widget::Releases)->assertSee(__('app.widget.releases.cold'));
    }

    public function test_the_run_tile_lists_the_last_commands_with_their_outcome(): void
    {
        $project = Project::query()->create(['name' => 'Testprojekt', 'path' => sys_get_temp_dir()]);

        CommandRun::query()->create([
            'project_id' => $project->getKey(), 'target' => 'test', 'kind' => 'make',
            'status' => RunStatus::Failed, 'exit_code' => 1, 'started_at' => now()->subMinutes(5),
            'finished_at' => now()->subMinutes(4),
        ]);

        $this->show(Widget::Runs)
            ->assertSee('make test')
            ->assertSee(RunStatus::Failed->label());
    }

    public function test_an_account_without_runs_sees_the_empty_state(): void
    {
        $this->show(Widget::Runs)->assertSee(__('app.widget.runs.empty'));
    }
}
