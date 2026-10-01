<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AbsenceType;
use App\Enums\EntryType;
use App\Models\Absence;
use App\Models\TimeEntry;
use App\Services\BulkEntryPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Filling several days at once. The scatter is the part worth testing: its entire purpose is
 * that the days do NOT come out identical.
 */
class BulkEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->login(['email' => 'dev@example.test']);
        Carbon::setTestNow('2026-09-07 10:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return list<string> five working days of one week */
    private function week(): array
    {
        return ['2026-09-07', '2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11'];
    }

    public function test_without_scatter_every_day_carries_the_same_times(): void
    {
        $plan = app(BulkEntryPlanner::class)->plan($this->week(), '09:00', '17:00', null, null, 0);

        $starts = collect($plan['entries'])->map(fn (array $e): string => $e['started_at']->format('H:i'))->unique();

        $this->assertCount(1, $starts);
        $this->assertSame('09:00', $starts->first());
        $this->assertCount(5, $plan['entries']);
    }

    public function test_with_scatter_the_days_differ_and_stay_inside_the_range(): void
    {
        $plan = app(BulkEntryPlanner::class)->plan($this->week(), '09:00', '17:00', null, null, 20);

        $starts = collect($plan['entries'])->map(fn (array $e): Carbon => $e['started_at']);

        // every start inside ±20 minutes of nine
        foreach ($starts as $start) {
            $this->assertLessThanOrEqual(20, abs($start->diffInMinutes($start->copy()->setTime(9, 0))));
        }

        /*
         * The point of the feature: five days must not all land on the same minute. With a
         * 41-minute window the chance of that happening by accident is about one in three
         * million, so a failure here is a broken scatter and not bad luck.
         */
        $this->assertGreaterThan(1, $starts->map(fn (Carbon $s): string => $s->format('H:i'))->unique()->count());
    }

    public function test_both_ends_of_a_day_move_independently(): void
    {
        $plan = app(BulkEntryPlanner::class)->plan(
            array_fill(0, 12, '2026-09-07'), '09:00', '17:00', null, null, 30,
        );

        // if the two ends shared one offset, every day would be exactly eight hours long
        $lengths = collect($plan['entries'])
            ->map(fn (array $e): int => (int) $e['started_at']->diffInMinutes($e['ended_at']))
            ->unique();

        $this->assertGreaterThan(1, $lengths->count());
    }

    public function test_a_break_splits_the_working_time_instead_of_overlapping_it(): void
    {
        $plan = app(BulkEntryPlanner::class)->plan(['2026-09-07'], '09:00', '17:00', '12:30', '13:00', 0);

        $this->assertCount(3, $plan['entries']);

        [$first, $break, $second] = $plan['entries'];

        $this->assertSame(EntryType::Work, $first['type']);
        $this->assertSame(EntryType::Break, $break['type']);
        $this->assertSame(EntryType::Work, $second['type']);

        // no overlap: each ends exactly where the next begins
        $this->assertTrue($first['ended_at']->equalTo($break['started_at']));
        $this->assertTrue($break['ended_at']->equalTo($second['started_at']));
    }

    public function test_the_break_keeps_its_exact_times_while_the_work_varies(): void
    {
        $days = array_fill(0, 14, '2026-09-07');

        $plan = app(BulkEntryPlanner::class)->plan($days, '09:00', '17:00', '12:30', '13:00', 30);

        $breaks = collect($plan['entries'])->filter(fn (array $e): bool => $e['type'] === EntryType::Break);

        /*
         * Every break identical, on every day. Scattering it would shorten it on about half of
         * them, and thirty minutes is a statutory minimum — a random four minutes short still
         * raises the compliance warning.
         */
        $this->assertCount(1, $breaks->map(fn (array $e): string => $e['started_at']->format('H:i'))->unique());
        $this->assertCount(1, $breaks->map(fn (array $e): string => $e['ended_at']->format('H:i'))->unique());
        $this->assertSame('12:30', $breaks->first()['started_at']->format('H:i'));
        $this->assertSame('13:00', $breaks->first()['ended_at']->format('H:i'));

        // and the working time around it still varies, or the scatter would be pointless
        $starts = collect($plan['entries'])
            ->filter(fn (array $e): bool => $e['type'] === EntryType::Work)
            ->map(fn (array $e): string => $e['started_at']->format('H:i'))
            ->unique();

        $this->assertGreaterThan(1, $starts->count());
    }

    public function test_a_scattered_day_never_books_less_break_than_was_asked_for(): void
    {
        $plan = app(BulkEntryPlanner::class)->plan(
            array_fill(0, 20, '2026-09-07'), '09:00', '17:00', '12:30', '13:00', 45,
        );

        foreach (collect($plan['entries'])->filter(fn (array $e): bool => $e['type'] === EntryType::Break) as $break) {
            $this->assertSame(30, (int) $break['started_at']->diffInMinutes($break['ended_at']));
        }
    }

    public function test_days_that_already_carry_a_booking_are_left_alone(): void
    {
        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => Carbon::parse('2026-09-08 08:00'),
            'ended_at' => Carbon::parse('2026-09-08 12:00'),
        ]);

        $plan = app(BulkEntryPlanner::class)->plan($this->week(), '09:00', '17:00', null, null, 0);

        $days = collect($plan['entries'])->map(fn (array $e): string => $e['started_at']->toDateString());

        $this->assertNotContains('2026-09-08', $days);
        $this->assertContains('2026-09-08', $plan['skipped'][BulkEntryPlanner::SKIP_BOOKED]);
        $this->assertCount(4, $plan['entries']);
    }

    public function test_weekends_and_absences_are_skipped_unless_asked_for(): void
    {
        Absence::query()->create([
            'type' => AbsenceType::Vacation,
            'starts_on' => '2026-09-09',
            'ends_on' => '2026-09-09',
        ]);

        $days = [...$this->week(), '2026-09-12', '2026-09-13'];

        $plan = app(BulkEntryPlanner::class)->plan($days, '09:00', '17:00', null, null, 0);
        $filled = collect($plan['entries'])->map(fn (array $e): string => $e['started_at']->toDateString());

        $this->assertNotContains('2026-09-09', $filled, 'the holiday was filled');
        $this->assertNotContains('2026-09-12', $filled, 'Saturday was filled');
        $this->assertNotContains('2026-09-13', $filled, 'Sunday was filled');

        // and with the switch on, they are filled
        $forced = app(BulkEntryPlanner::class)->plan($days, '09:00', '17:00', null, null, 0, skipExempt: false);

        $this->assertCount(7, $forced['entries']);
    }

    public function test_a_scatter_wide_enough_to_invert_a_day_produces_nothing_for_it(): void
    {
        // a ten-minute stretch with an hour of scatter can end before it starts
        $plan = app(BulkEntryPlanner::class)->plan(
            array_fill(0, 40, '2026-09-07'), '09:00', '09:10', null, null, 60,
        );

        foreach ($plan['entries'] as $entry) {
            $this->assertTrue($entry['ended_at']->greaterThan($entry['started_at']));
        }
    }

    public function test_the_endpoint_writes_them_and_reports_what_it_skipped(): void
    {
        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => Carbon::parse('2026-09-08 08:00'),
            'ended_at' => Carbon::parse('2026-09-08 12:00'),
        ]);

        $this->post(route('entries.bulk'), [
            'tage' => $this->week(),
            'von' => '09:00',
            'bis' => '17:00',
            'pause_von' => '12:30',
            'pause_bis' => '13:00',
            'streuung' => 10,
        ])->assertRedirect()->assertSessionHas('status');

        // four days, three entries each, plus the one that was already there
        $this->assertSame(13, TimeEntry::query()->count());
    }

    public function test_a_start_time_in_the_past_starts_the_timer_there(): void
    {
        $this->post(route('timer.start'), ['type' => EntryType::Work->value, 'ab' => '08:15'])
            ->assertRedirect();

        $running = TimeEntry::query()->running()->first();

        $this->assertNotNull($running);
        $this->assertSame('08:15', $running->started_at->format('H:i'));
    }

    public function test_a_start_time_in_the_future_falls_back_to_now(): void
    {
        // the clock is at 10:00; 23:00 today has not happened yet
        $this->post(route('timer.start'), ['type' => EntryType::Work->value, 'ab' => '23:00']);

        $running = TimeEntry::query()->running()->first();

        $this->assertSame('10:00', $running->started_at->format('H:i'));
    }
}
