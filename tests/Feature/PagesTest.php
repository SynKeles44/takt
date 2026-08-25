<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EntryType;
use App\Models\TimeEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->login();

        Carbon::setTestNow('2026-08-20 18:00:00');
    }

    public function test_the_dashboard_shows_todays_totals(): void
    {
        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-20 09:00:00',
            'ended_at' => '2026-08-20 12:00:00',
        ]);

        TimeEntry::query()->create([
            'type' => EntryType::Break,
            'started_at' => '2026-08-20 12:00:00',
            'ended_at' => '2026-08-20 12:45:00',
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('3h 00m')
            ->assertSee('45m')
            ->assertSee(__('app.timer.start_work'));
    }

    public function test_the_dashboard_shows_the_running_timer_controls(): void
    {
        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-20 17:00:00',
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('app.timer.to_break'))
            ->assertSee(__('app.timer.end_day'))
            ->assertDontSee(__('app.timer.idle_title'));
    }

    public function test_the_live_ticking_totals_exclude_the_running_entry_from_their_base(): void
    {
        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-20 09:00:00',
            'ended_at' => '2026-08-20 11:00:00',
        ]);

        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-20 17:00:00',
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('3h 00m')
            ->assertSee('data-base="7200"', escape: false);
    }

    public function test_the_dashboard_stays_anchored_on_today_while_the_chart_defaults_to_this_week(): void
    {
        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-17 09:00:00',
            'ended_at' => '2026-08-17 17:00:00',
        ]);

        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-20 09:00:00',
            'ended_at' => '2026-08-20 11:30:00',
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Donnerstag, 20. August')
            ->assertSee('2h 30m')
            ->assertSee(__('app.chart.title'))
            ->assertSee('10h 30m');
    }

    public function test_the_dashboard_chart_can_show_another_week(): void
    {
        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-11 09:00:00',
            'ended_at' => '2026-08-15 09:00:00',
        ]);

        $this->get(route('dashboard', ['woche' => '2026-08-10']))
            ->assertOk()
            ->assertSee('KW 33 · 10. Aug – 16. Aug')
            ->assertSee(__('app.week.current'))
            ->assertDontSee(__('app.chart.title'));
    }

    public function test_an_invalid_chart_week_is_rejected(): void
    {
        $this->get(route('dashboard', ['woche' => 'letzte']))
            ->assertSessionHasErrors('woche');
    }

    public function test_the_history_defaults_to_the_current_week(): void
    {
        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-18 09:00:00',
            'ended_at' => '2026-08-18 17:00:00',
        ]);

        $this->get(route('history'))
            ->assertOk()
            ->assertSee('8h 00m')
            ->assertSee('34');
    }

    public function test_the_history_can_show_another_week(): void
    {
        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-11 09:00:00',
            'ended_at' => '2026-08-11 15:00:00',
        ]);

        $this->get(route('history', ['from' => '2026-08-10']))
            ->assertOk()
            ->assertSee('6h 00m');

        $this->get(route('history'))
            ->assertOk()
            ->assertDontSee('6h 00m');
    }

    public function test_an_invalid_week_parameter_is_rejected(): void
    {
        $this->get(route('history', ['from' => 'gestern']))
            ->assertSessionHasErrors('from');
    }
}
