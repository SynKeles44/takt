<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AbsenceType;
use App\Enums\EntryType;
use App\Enums\TicketColumn;
use App\Models\Absence;
use App\Models\DayNote;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Services\TicketBoard;
use App\Services\TicketFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * The ticket file — one page per ticket, and the timeline that answers "what happened here and
 * when" without opening three tools.
 */
class TicketFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Strict rather than catch-all: Http::fake() with no arguments registers a stub that
         * matches everything, and a later fake is MERGED behind it — so the specific stub never
         * gets a look in. This way an unfaked request fails loudly instead of quietly returning
         * an empty 200 that looks like "Linear knows nothing".
         */
        Http::preventStrayRequests();
        $this->login(['email' => 'dev@example.test']);
        Carbon::setTestNow(Carbon::now());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** A repository whose history mentions one ticket and one that only looks similar. */
    private function repository(): string
    {
        $path = sys_get_temp_dir().'/takt-file-'.bin2hex(random_bytes(4));

        mkdir($path, 0o755, true);
        file_put_contents($path.'/README.md', "hello\n");

        foreach ([
            ['git', 'init', '-q', '-b', 'main'],
            ['git', 'config', 'user.email', 'dev@example.test'],
            ['git', 'config', 'user.name', 'Dev'],
            ['git', 'add', '-A'],
            ['git', 'commit', '-q', '-m', 'feat(zeit): Buchung korrigieren (COR-6839)'],
            ['git', 'commit', '-q', '--allow-empty', '-m', 'chore: andere Nummer COR-68391'],
            ['git', 'branch', 'feat/COR-6839/buchung'],
        ] as $command) {
            Process::path($path)->run($command)->throw();
        }

        return $path;
    }

    public function test_the_id_is_matched_whole_so_a_longer_number_does_not_count(): void
    {
        $path = $this->repository();
        Project::query()->create(['name' => 'Testrepo', 'path' => $path]);

        $file = app(TicketFile::class)->for(auth()->user(), 'COR-6839');

        $this->assertCount(1, $file['commits']);
        $this->assertStringContainsString('Buchung korrigieren', $file['commits']->first()['subject']);
        $this->assertCount(1, $file['branches']);

        Process::run(['rm', '-rf', $path]);
    }

    public function test_the_timeline_merges_commits_time_notes_and_the_column_move(): void
    {
        $path = $this->repository();
        Project::query()->create(['name' => 'Testrepo', 'path' => $path]);

        $board = app(TicketBoard::class);
        $ticket = $board->row('COR-6839');
        $board->place('COR-6839', TicketColumn::Waiting);
        $board->waitingReason('COR-6839', 'Review von Weber');

        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'ticket_id' => $ticket->getKey(),
            'started_at' => Carbon::today()->setTime(9, 0),
            'ended_at' => Carbon::today()->setTime(10, 0),
        ]);

        DayNote::query()->create([
            'day' => Carbon::today()->toDateString(),
            'body' => 'COR-6839 hängt an der Freigabe',
        ]);

        $file = app(TicketFile::class)->for(auth()->user(), 'COR-6839');
        $kinds = $file['timeline']->pluck('kind')->unique()->sort()->values()->all();

        $this->assertSame(['branch', 'column', 'commit', 'note', 'time'], $kinds);
        $this->assertSame(3600, $file['booked']);
        $this->assertCount(1, $file['notes']);

        // newest first, so the page opens on what just happened
        $stamps = $file['timeline']->map(fn (array $event): string => $event['at']->toIso8601String())->all();
        $sorted = $stamps;
        rsort($sorted);
        $this->assertSame($sorted, $stamps);

        Process::run(['rm', '-rf', $path]);
    }

    public function test_an_absence_that_overlapped_is_surfaced_and_home_office_is_not(): void
    {
        $board = app(TicketBoard::class);
        $ticket = $board->create('Eigenes');

        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'ticket_id' => $ticket->getKey(),
            'started_at' => Carbon::today()->subDays(10)->setTime(9, 0),
            'ended_at' => Carbon::today()->subDays(10)->setTime(10, 0),
        ]);

        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'ticket_id' => $ticket->getKey(),
            'started_at' => Carbon::today()->setTime(9, 0),
            'ended_at' => Carbon::today()->setTime(10, 0),
        ]);

        Absence::query()->create([
            'type' => AbsenceType::Vacation,
            'starts_on' => Carbon::today()->subDays(6)->toDateString(),
            'ends_on' => Carbon::today()->subDays(4)->toDateString(),
        ]);

        // a marker on a normal working day is no explanation for a ticket lying still
        Absence::query()->create([
            'type' => AbsenceType::HomeOffice,
            'starts_on' => Carbon::today()->subDays(5)->toDateString(),
            'ends_on' => Carbon::today()->subDays(5)->toDateString(),
        ]);

        $file = app(TicketFile::class)->for(auth()->user(), $ticket->key);

        $this->assertCount(1, $file['absences']);
        $this->assertSame(AbsenceType::Vacation, $file['absences']->first()->type);
    }

    public function test_a_local_ticket_is_never_looked_up_in_linear(): void
    {
        Http::fake();

        $ticket = app(TicketBoard::class)->create('Eigenes');

        $file = app(TicketFile::class)->for(auth()->user(), $ticket->key);

        $this->assertNull($file['issue']);
        Http::assertNothingSent();
    }

    /** @param list<array<string, mixed>> $pulls */
    private function withLinearAndPulls(string $state, string $type, array $pulls): void
    {
        // setUp already signed a user in; giving them the keys beats creating a second one
        auth()->user()->update(['linear_token' => 'lin_api_test', 'github_token' => 'ghp_test']);

        Http::fake(['api.linear.app/graphql' => Http::response(['data' => ['issues' => ['nodes' => [[
            'identifier' => 'COR-6839',
            'title' => 'Buchung korrigieren',
            'url' => 'https://linear.app/acme/issue/COR-6839',
            'state' => ['name' => $state, 'type' => $type],
            'assignee' => ['displayName' => 'Seymen'],
            'priorityLabel' => 'High',
        ]]]]])]);

        cache()->put('reviews.'.auth()->id(), [
            'mine' => $pulls,
            'incoming' => [],
            'repositories' => [],
            'login' => 'ich',
            'error' => null,
            'fetched_at' => Carbon::now()->toIso8601String(),
        ], 600);
    }

    /** @return array<string, mixed> */
    private function pull(bool $draft): array
    {
        return [
            'title' => 'fix: Buchung korrigieren (COR-6839)',
            'number' => 42,
            'url' => 'https://github.test/pr/42',
            'repository' => 'acme/web',
            'draft' => $draft,
            'updated_at' => Carbon::now()->toIso8601String(),
            'created_at' => Carbon::now()->subDay()->toIso8601String(),
        ];
    }

    public function test_done_in_linear_with_an_open_pull_request_is_a_contradiction(): void
    {
        $this->withLinearAndPulls('Done', 'completed', [$this->pull(draft: false)]);

        $file = app(TicketFile::class)->for(auth()->user(), 'COR-6839');

        $this->assertSame(__('app.ticket.clash.done_open'), $file['contradiction']);
    }

    public function test_a_draft_pull_request_while_linear_says_review_is_a_contradiction(): void
    {
        $this->withLinearAndPulls('In Review', 'started', [$this->pull(draft: true)]);

        $file = app(TicketFile::class)->for(auth()->user(), 'COR-6839');

        $this->assertSame(__('app.ticket.clash.draft_review'), $file['contradiction']);
    }

    public function test_not_started_with_a_finished_pull_request_is_a_contradiction(): void
    {
        $this->withLinearAndPulls('Todo', 'unstarted', [$this->pull(draft: false)]);

        $file = app(TicketFile::class)->for(auth()->user(), 'COR-6839');

        $this->assertSame(__('app.ticket.clash.todo_ready'), $file['contradiction']);
    }

    public function test_agreement_stays_silent(): void
    {
        $this->withLinearAndPulls('In Review', 'started', [$this->pull(draft: false)]);

        $file = app(TicketFile::class)->for(auth()->user(), 'COR-6839');

        $this->assertNull($file['contradiction']);
    }

    public function test_the_page_opens_and_shows_the_local_halves(): void
    {
        $board = app(TicketBoard::class);
        $ticket = $board->create('Serverwechsel prüfen', 'Vorher Backup');
        $board->notes($ticket->key, 'Erst Weber fragen');
        $board->place($ticket->key, TicketColumn::Waiting);

        $this->get(route('tickets.show', ['key' => $ticket->key]))
            ->assertOk()
            ->assertSee('Serverwechsel prüfen')
            ->assertSee('Erst Weber fragen')
            ->assertSee(__('app.ticket.waiting_reason'))
            ->assertSee(__('app.ticket.promote'));
    }
}
