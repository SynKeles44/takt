<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\EntryType;
use App\Models\AwayGap;
use App\Models\TimeEntry;
use App\Services\AwayTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * An absence that crosses midnight is a forgotten timer, not a break.
 *
 * The question the gap asks has three answers and all of them assume it happened inside one
 * working day: booking fifteen hours as a break, or cutting the work at 14:47 and restarting it
 * at 06:17 the next morning, are both wrong answers to a question that should not be asked.
 */
class AwayTimeDayBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->login();
    }

    public function test_a_gap_that_crosses_midnight_is_not_recorded(): void
    {
        $this->runningWorkSince('2026-10-01 09:00');

        $gap = app(AwayTime::class)->record(
            Carbon::parse('2026-10-01 14:47'),
            Carbon::parse('2026-10-02 06:17'),
        );

        $this->assertNull($gap);
        $this->assertSame(0, AwayGap::query()->count());
    }

    /** The same length, inside one day, is exactly what this feature is for. */
    public function test_a_long_gap_inside_one_day_is_still_recorded(): void
    {
        $this->runningWorkSince('2026-10-01 06:00');

        $gap = app(AwayTime::class)->record(
            Carbon::parse('2026-10-01 07:00'),
            Carbon::parse('2026-10-01 22:30'),
        );

        $this->assertNotNull($gap);
    }

    /** A gap recorded before this rule existed must stop being offered too. */
    public function test_an_older_cross_day_gap_is_no_longer_pending(): void
    {
        AwayGap::query()->create([
            'started_at' => Carbon::parse('2026-09-30 14:47'),
            'ended_at' => Carbon::parse('2026-10-01 06:17'),
        ]);

        $this->assertNull(app(AwayTime::class)->pending());
    }

    public function test_a_same_day_gap_is_still_pending(): void
    {
        AwayGap::query()->create([
            'started_at' => Carbon::parse('2026-10-01 11:00'),
            'ended_at' => Carbon::parse('2026-10-01 12:30'),
        ]);

        $this->assertNotNull(app(AwayTime::class)->pending());
    }

    private function runningWorkSince(string $at): void
    {
        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => Carbon::parse($at),
            'ended_at' => null,
        ]);
    }
}
