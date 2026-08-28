<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EntryType;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Services\Tickets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->login(['github_token' => 'ghp_test', 'email' => 'dev@example.test']);
        Carbon::setTestNow('2026-08-28 18:00:00');
    }

    /** A repository with two commits on two tickets and a ticket branch. */
    private function repository(): string
    {
        $path = sys_get_temp_dir().'/takt-tickets-'.bin2hex(random_bytes(4));

        mkdir($path, 0o755, true);
        file_put_contents($path.'/README.md', "hello\n");

        foreach ([
            ['git', 'init', '-q', '-b', 'main'],
            ['git', 'config', 'user.email', 'dev@example.test'],
            ['git', 'config', 'user.name', 'Dev'],
            ['git', 'add', '-A'],
            ['git', 'commit', '-q', '-m', 'feat(zeit): Buchung korrigieren (COR-6839)'],
            ['git', 'commit', '-q', '--allow-empty', '-m', 'chore: Aufräumen für DEV-5472'],
            ['git', 'branch', 'feat/COR-6839/buchung'],
        ] as $command) {
            Process::path($path)->run($command)->throw();
        }

        return $path;
    }

    public function test_ids_are_read_out_of_any_text(): void
    {
        $tickets = app(Tickets::class);

        $this->assertSame(['COR-6839'], $tickets->ids('feat(zeit): etwas (COR-6839)'));
        $this->assertSame(['COR-6839', 'DEV-12'], $tickets->ids('COR-6839 und DEV-12'));
        $this->assertSame(['COR-6839'], $tickets->ids('COR-6839 zweimal COR-6839'));
        $this->assertSame([], $tickets->ids('kein ticket hier'));
        $this->assertSame([], $tickets->ids('utf-8 und iso-9001 sind keine tickets'));
    }

    public function test_commits_and_branches_land_on_their_ticket(): void
    {
        $path = $this->repository();
        Project::query()->create(['name' => 'Testrepo', 'path' => $path]);

        $tickets = app(Tickets::class)->collect(auth()->user(), 30)['tickets']->keyBy('id');

        $this->assertTrue($tickets->has('COR-6839'));
        $this->assertTrue($tickets->has('DEV-5472'));
        $this->assertCount(1, $tickets['COR-6839']['commits']);
        $this->assertCount(1, $tickets['COR-6839']['branches']);
        $this->assertSame(['Testrepo'], $tickets['COR-6839']['projects']);

        Process::run(['rm', '-rf', $path]);
    }

    public function test_the_days_work_is_split_across_the_tickets_of_that_day(): void
    {
        $path = $this->repository();
        Project::query()->create(['name' => 'Testrepo', 'path' => $path]);

        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => Carbon::today()->setTime(9, 0),
            'ended_at' => Carbon::today()->setTime(17, 0),
        ]);

        $tickets = app(Tickets::class)->collect(auth()->user(), 30)['tickets']->keyBy('id');

        // eight hours, two tickets committed today
        $this->assertSame(4 * 3600, $tickets['COR-6839']['seconds']);
        $this->assertSame(4 * 3600, $tickets['DEV-5472']['seconds']);

        Process::run(['rm', '-rf', $path]);
    }

    public function test_a_ticket_without_commits_gets_no_estimated_time(): void
    {
        $path = $this->repository();
        Project::query()->create(['name' => 'Testrepo', 'path' => $path]);

        Process::path($path)->run(['git', 'branch', 'feat/COR-9999/nur-ein-branch'])->throw();

        $tickets = app(Tickets::class)->collect(auth()->user(), 30)['tickets']->keyBy('id');

        $this->assertSame(0, $tickets['COR-9999']['seconds']);
        $this->assertSame([], $tickets['COR-9999']['commits']);

        Process::run(['rm', '-rf', $path]);
    }

    public function test_pull_requests_out_of_the_cache_are_named_after_the_project(): void
    {
        Project::query()->create(['name' => 'Webshop', 'path' => sys_get_temp_dir(), 'repository' => 'acme/web']);

        cache()->put('reviews.'.auth()->id(), [
            'mine' => [[
                'title' => 'fix(zeit): Doppelbuchung (COR-7000)',
                'number' => 42,
                'url' => 'https://github.test/pr/42',
                'repository' => 'acme/web',
                'draft' => false,
                'updated_at' => '2026-08-27T10:00:00+02:00',
                'created_at' => '2026-08-26T10:00:00+02:00',
            ]],
            'incoming' => [],
            'repositories' => [],
            'login' => 'ich',
            'error' => null,
            'fetched_at' => '2026-08-27T10:00:00+02:00',
        ], 600);

        $ticket = app(Tickets::class)->collect(auth()->user(), 30)['tickets']->keyBy('id')['COR-7000'];

        $this->assertSame(['Webshop'], $ticket['projects']);
        $this->assertCount(1, $ticket['pulls']);
    }

    public function test_the_page_lists_tickets_and_filters_by_term(): void
    {
        $path = $this->repository();
        Project::query()->create(['name' => 'Testrepo', 'path' => $path]);

        $this->get(route('tickets', ['tage' => 30]))
            ->assertOk()
            ->assertSee('COR-6839')
            ->assertSee('DEV-5472')
            ->assertSee(__('app.tickets.estimate_hint'));

        $this->get(route('tickets', ['tage' => 30, 'q' => 'cor-6839']))
            ->assertOk()
            ->assertSee('COR-6839')
            ->assertDontSee('DEV-5472');

        Process::run(['rm', '-rf', $path]);
    }

    public function test_an_absurd_window_is_rejected(): void
    {
        $this->get(route('tickets', ['tage' => 4000]))->assertSessionHasErrors('tage');
    }
}
