<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EntryType;
use App\Enums\Widget;
use App\Models\AwayGap;
use App\Models\TimeEntry;
use App\Services\AwayTime;
use App\Services\TimeTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AwayTimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->login();
        Carbon::setTestNow('2026-08-28 14:00:00');
    }

    private function running(string $from = '09:00'): TimeEntry
    {
        return TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => Carbon::today()->setTimeFromTimeString($from),
        ]);
    }

    private function record(string $from, string $to): ?AwayGap
    {
        return app(AwayTime::class)->record(
            Carbon::today()->setTimeFromTimeString($from),
            Carbon::today()->setTimeFromTimeString($to),
        );
    }

    public function test_a_gap_while_work_runs_is_recorded(): void
    {
        $this->running();

        $gap = $this->record('11:00', '12:30');

        $this->assertNotNull($gap);
        $this->assertSame(90 * 60, $gap->seconds());
    }

    public function test_a_short_lock_is_not_worth_recording(): void
    {
        $this->running();

        $this->assertNull($this->record('11:00', '11:03'));
    }

    public function test_nothing_is_recorded_when_no_work_was_running(): void
    {
        $this->assertNull($this->record('11:00', '12:30'));
    }

    public function test_sleep_and_lock_reporting_the_same_absence_stay_one_gap(): void
    {
        $this->running();

        $first = $this->record('11:00', '12:30');
        $second = $this->record('11:05', '12:40');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, AwayGap::query()->count());
        $this->assertSame('12:40', $second->fresh()->ended_at->format('H:i'));
    }

    public function test_booking_it_as_a_break_splits_the_work_around_it(): void
    {
        $this->running();
        $gap = $this->record('11:00', '12:00');

        app(AwayTime::class)->asBreak($gap, app(TimeTracker::class));

        $entries = TimeEntry::query()->orderBy('started_at')->get();

        $this->assertSame('09:00', $entries[0]->started_at->format('H:i'));
        $this->assertSame('11:00', $entries[0]->ended_at->format('H:i'));
        $this->assertSame(EntryType::Break, $entries[1]->type);
        $this->assertSame('12:00', $entries[1]->ended_at->format('H:i'));
        $this->assertSame('12:00', $entries[2]->started_at->format('H:i'));
        $this->assertNull($entries[2]->ended_at);
        $this->assertNotNull($gap->fresh()->resolved_at);
    }

    public function test_shortening_cuts_the_work_and_starts_again_afterwards(): void
    {
        $this->running();
        $gap = $this->record('11:00', '12:00');

        app(AwayTime::class)->shorten($gap, app(TimeTracker::class));

        $entries = TimeEntry::query()->orderBy('started_at')->get();

        $this->assertCount(2, $entries);
        $this->assertSame('11:00', $entries[0]->ended_at->format('H:i'));
        $this->assertSame('12:00', $entries[1]->started_at->format('H:i'));
        $this->assertSame(2 * 3600 + 2 * 3600, app(TimeTracker::class)->totalsForDay(Carbon::today())['work']);
    }

    public function test_keeping_it_changes_no_time_but_closes_the_question(): void
    {
        $this->running();
        $gap = $this->record('11:00', '12:00');

        app(AwayTime::class)->keep($gap);

        $this->assertSame(1, TimeEntry::query()->count());
        $this->assertNull(app(AwayTime::class)->pending());
    }

    public function test_the_shell_reports_a_gap_and_the_timer_asks_about_it(): void
    {
        $this->running();

        $this->postJson(route('away.store'), [
            'from' => Carbon::today()->setTime(11, 0)->toIso8601String(),
            'to' => Carbon::today()->setTime(12, 0)->toIso8601String(),
        ])->assertOk()->assertJson(['recorded' => true]);

        $this->get(route('dashboard.widget', ['widget' => Widget::Timer->value]))
            ->assertOk()
            ->assertSee(__('app.away.title'))
            ->assertSee(__('app.away.break'));
    }

    public function test_an_answer_the_page_did_not_offer_is_rejected(): void
    {
        $this->running();
        $gap = $this->record('11:00', '12:00');

        $this->put(route('away.update', $gap), ['answer' => 'delete-everything'])
            ->assertSessionHasErrors('answer');
    }
}
