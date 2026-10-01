<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EntryType;
use App\Models\TimeEntry;
use App\Services\Sprints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * The sprint view: Linear's cycle, filled with this app's measurements.
 *
 * The split is what is being tested. Linear says which tickets belong to the sprint and when it
 * runs; the timesheet says what was spent. Two numbers come out of that and they are deliberately
 * not reconciled — time booked ON the sprint's tickets and time booked DURING the sprint are
 * different quantities, and a view that showed only one would be answering a different question
 * than the one asked.
 */
class SprintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Process::fake();
        Http::preventStrayRequests();
        Carbon::setTestNow('2026-10-01 12:00:00');

        $this->login(['linear_token' => 'lin_api_test']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function fakeCycle(array $issues): void
    {
        Http::fake(['api.linear.app/graphql' => Http::response(['data' => ['viewer' => ['assignedIssues' => [
            'nodes' => $issues,
        ]]]])]);
    }

    private function issue(string $id, string $type, ?string $completedAt = null, ?float $estimate = null): array
    {
        return [
            'identifier' => $id,
            'title' => 'Ticket '.$id,
            'url' => 'https://linear.app/acme/issue/'.$id,
            'updatedAt' => '2026-09-30T08:00:00.000Z',
            'completedAt' => $completedAt,
            'estimate' => $estimate,
            'state' => ['name' => ucfirst($type), 'type' => $type],
            'team' => ['key' => 'COR', 'name' => 'Core'],
            'cycle' => ['id' => 'c11', 'number' => 11, 'name' => 'Cycle 11', 'startsAt' => '2026-09-28T00:00:00.000Z', 'endsAt' => '2026-10-11T00:00:00.000Z'],
        ];
    }

    public function test_a_cycle_becomes_a_sprint_with_its_own_counts(): void
    {
        $this->fakeCycle([
            $this->issue('COR-1', 'completed', '2026-09-29T15:00:00.000Z', 3),
            $this->issue('COR-2', 'started', null, 2),
            $this->issue('COR-3', 'unstarted', null, 1),
        ]);

        $sprint = app(Sprints::class)->recent(auth()->user())['sprints']->first();

        $this->assertSame(3, $sprint['scope']);
        $this->assertSame(1, $sprint['done']);
        $this->assertSame(1, $sprint['started']);
        $this->assertSame(6.0, $sprint['points']);
        $this->assertSame(3.0, $sprint['points_done']);
        $this->assertTrue($sprint['current']);
        $this->assertCount(14, $sprint['days']);
    }

    /**
     * Work during the sprint's days and work on the sprint's tickets are counted apart, because
     * they answer different questions — and a day that carries neither still gets its bar.
     */
    public function test_the_hours_are_counted_twice_on_purpose(): void
    {
        $this->fakeCycle([$this->issue('COR-1', 'completed', '2026-09-29T15:00:00.000Z')]);

        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => Carbon::parse('2026-09-29 09:00'),
            'ended_at' => Carbon::parse('2026-09-29 11:00'),
        ]);

        $sprint = app(Sprints::class)->recent(auth()->user())['sprints']->first();

        $this->assertSame(7200, $sprint['seconds_in_window']);
        // nothing is booked against the ticket itself, so that measurement stays at zero
        $this->assertSame(0, $sprint['seconds_on_issues']);

        $day = collect($sprint['days'])->firstWhere(fn (array $row): bool => $row['date']->toDateString() === '2026-09-29');

        $this->assertSame(7200, $day['seconds']);
        $this->assertSame(1, $day['closed']);
    }

    public function test_an_issue_outside_any_cycle_belongs_to_no_sprint(): void
    {
        $this->fakeCycle([[...$this->issue('COR-9', 'started'), 'cycle' => null]]);

        $this->assertTrue(app(Sprints::class)->recent(auth()->user())['sprints']->isEmpty());
    }

    public function test_the_page_shows_the_sprint(): void
    {
        $this->fakeCycle([$this->issue('COR-1', 'completed', '2026-09-29T15:00:00.000Z', 3)]);

        $this->get(route('tickets', ['ansicht' => 'sprints']))
            ->assertOk()
            ->assertSee('Cycle 11')
            ->assertSee(__('app.sprint.current'))
            ->assertSee(__('app.sprint.scope'));
    }
}
