<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EntryType;
use App\Models\TimeEntry;
use App\Services\TimeTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BalanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->login();

        Carbon::setTestNow('2026-08-20 14:00:00');
    }

    private function workDay(string $date, string $from, string $to): void
    {
        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => $date.' '.$from,
            'ended_at' => $date.' '.$to,
        ]);
    }

    public function test_days_on_target_produce_a_zero_balance(): void
    {
        $this->workDay('2026-08-18', '09:00', '17:00');
        $this->workDay('2026-08-19', '08:00', '16:00');

        $balance = app(TimeTracker::class)->balance(28_800);

        $this->assertSame(0, $balance['seconds']);
        $this->assertSame(2, $balance['days']);
    }

    public function test_overtime_and_undertime_add_up(): void
    {
        $this->workDay('2026-08-18', '09:00', '18:00');
        $this->workDay('2026-08-19', '08:00', '15:00');
        $this->workDay('2026-08-17', '08:00', '17:00');

        $this->assertSame(3_600, app(TimeTracker::class)->balance(28_800)['seconds']);
    }

    public function test_split_days_are_summed_per_day(): void
    {
        $this->workDay('2026-08-19', '08:00', '12:00');
        $this->workDay('2026-08-19', '12:30', '17:30');

        TimeEntry::query()->create([
            'type' => EntryType::Break,
            'started_at' => '2026-08-19 12:00:00',
            'ended_at' => '2026-08-19 12:30:00',
        ]);

        $balance = app(TimeTracker::class)->balance(28_800);

        $this->assertSame(3_600, $balance['seconds']);
        $this->assertSame(1, $balance['days']);
    }

    public function test_today_counts_towards_the_balance(): void
    {
        $this->workDay('2026-08-19', '08:00', '17:00');
        $this->workDay('2026-08-20', '08:00', '10:00');

        $balance = app(TimeTracker::class)->balance(28_800);

        $this->assertSame(-18_000, $balance['seconds']);
        $this->assertSame(2, $balance['days']);
    }

    public function test_a_finished_day_today_is_reflected_immediately(): void
    {
        $this->workDay('2026-08-20', '08:00', '12:00');
        $this->workDay('2026-08-20', '12:40', '17:10');

        $balance = app(TimeTracker::class)->balance(28_800);

        $this->assertSame(1_800, $balance['seconds']);
        $this->assertSame(1, $balance['days']);
    }

    public function test_future_entries_are_excluded(): void
    {
        $this->workDay('2026-08-21', '08:00', '10:00');

        $balance = app(TimeTracker::class)->balance(28_800);

        $this->assertSame(0, $balance['seconds']);
        $this->assertSame(0, $balance['days']);
    }

    public function test_a_running_entry_is_excluded(): void
    {
        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-19 09:00:00',
        ]);

        $balance = app(TimeTracker::class)->balance(28_800);

        $this->assertSame(0, $balance['seconds']);
        $this->assertSame(0, $balance['days']);
    }

    public function test_the_dashboard_shows_the_balance(): void
    {
        $this->workDay('2026-08-19', '08:00', '17:00');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('app.stats.balance'))
            ->assertSee('+1h 00m')
            ->assertSee('+1,00 h · 1 Buchungstage');
    }

    public function test_the_dashboard_shows_a_negative_balance(): void
    {
        $this->workDay('2026-08-19', '08:00', '14:00');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('−2h 00m')
            ->assertSee('−2,00 h', escape: false);
    }
}
