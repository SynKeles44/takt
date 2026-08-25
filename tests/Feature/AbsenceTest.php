<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EntryType;
use App\Models\Absence;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Holidays;
use App\Services\TimeTracker;
use App\Services\WorkCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AbsenceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->login(['holiday_region' => 'NW', 'weekly_hours' => 40, 'working_days' => 5]);

        Carbon::setTestNow('2026-08-20 18:00:00');
    }

    public function test_an_absence_can_be_booked_and_deleted(): void
    {
        $this->post(route('absences.store'), [
            'type' => 'vacation',
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-04',
            'note' => 'Sommerurlaub',
        ])->assertRedirect()->assertSessionHas('status');

        $absence = Absence::query()->firstOrFail();

        $this->assertSame(4, $absence->workdays());

        $this->get(route('absences'))->assertOk()->assertSee('Sommerurlaub');

        $this->delete(route('absences.destroy', $absence))->assertRedirect();
        $this->assertDatabaseCount('absences', 0);
    }

    public function test_an_end_before_the_start_is_rejected(): void
    {
        $this->post(route('absences.store'), [
            'type' => 'vacation',
            'starts_on' => '2026-09-04',
            'ends_on' => '2026-09-01',
        ])->assertSessionHasErrors('ends_on');
    }

    public function test_holidays_follow_the_chosen_federal_state(): void
    {
        $holidays = app(Holidays::class);

        $nrw = $holidays->forYear(2026, 'NW');
        $bavaria = $holidays->forYear(2026, 'BY');

        $this->assertSame('Tag der Deutschen Einheit', $nrw['2026-10-03']);
        $this->assertArrayHasKey('2026-11-01', $nrw);
        $this->assertArrayNotHasKey('2026-01-06', $nrw);
        $this->assertArrayHasKey('2026-01-06', $bavaria);
        $this->assertSame('Ostermontag', $nrw['2026-04-06']);
    }

    public function test_the_region_select_lists_every_state(): void
    {
        $this->get(route('settings'))
            ->assertOk()
            ->assertSee('Nordrhein-Westfalen')
            ->assertSee('Bayern')
            ->assertSee('Sachsen-Anhalt');

        $this->assertCount(16, Holidays::regions());
    }

    public function test_an_absence_day_carries_no_target_but_keeps_its_booked_time(): void
    {
        Absence::query()->create(['type' => 'vacation', 'starts_on' => '2026-08-18', 'ends_on' => '2026-08-18']);

        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-17 09:00:00',
            'ended_at' => '2026-08-17 17:00:00',
        ]);

        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-18 09:00:00',
            'ended_at' => '2026-08-18 11:00:00',
        ]);

        $calendar = app(WorkCalendar::class);
        $exempt = $calendar->exemptDatesForBalance($this->user, Carbon::parse('2026-08-20'));

        $balance = app(TimeTracker::class)->balance($this->user->dailyTargetSeconds(), Carbon::parse('2026-08-20'), $exempt);

        $this->assertContains('2026-08-18', $exempt);
        $this->assertSame(1, $balance['days']);
        $this->assertSame(7200, $balance['seconds']);
    }

    public function test_the_dashboard_warns_about_a_missing_break(): void
    {
        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-20 08:00:00',
            'ended_at' => '2026-08-20 15:00:00',
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('es fehlen 30m Pause', false)
            ->assertSee('30 Minuten Pflicht', false);
    }

    public function test_the_dashboard_warns_when_the_daily_maximum_is_exceeded(): void
    {
        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-20 06:00:00',
            'ended_at' => '2026-08-20 17:30:00',
        ]);

        TimeEntry::query()->create([
            'type' => EntryType::Break,
            'started_at' => '2026-08-20 12:00:00',
            'ended_at' => '2026-08-20 12:50:00',
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('über der Höchstarbeitszeit von 10 Stunden', false);
    }

    public function test_an_absence_of_another_account_is_not_reachable(): void
    {
        $other = User::factory()->create();

        $absence = Absence::query()->forceCreate([
            'user_id' => $other->id,
            'type' => 'sick',
            'starts_on' => '2026-08-18',
            'ends_on' => '2026-08-18',
            'note' => 'Fremd',
        ]);

        $this->get(route('absences'))->assertOk()->assertDontSee('Fremd');
        $this->delete(route('absences.destroy', $absence))->assertNotFound();
    }
}
