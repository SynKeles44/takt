<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * A "back to now" button that only appears when it has something to do moves every button next to
 * it. It stays in place and stops being a link instead.
 *
 * On the dated pages that state is now what the travelling marker sits on, so it is read off the
 * marked step rather than off a dimming class — the week chart still uses the class and is checked
 * for it directly.
 */
class NavigationButtonsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->login();
        Carbon::setTestNow('2026-08-25 10:00:00');
    }

    private const string HERE = 'data-date-step aria-current="page"';

    public function test_the_history_keeps_its_button_in_the_current_week(): void
    {
        $this->get(route('history'))
            ->assertOk()
            ->assertSee(__('app.week.current'))
            ->assertSee(self::HERE, escape: false);

        $this->get(route('history', ['from' => '2026-08-17']))
            ->assertOk()
            ->assertSee(__('app.week.current'))
            ->assertDontSee(self::HERE, escape: false);
    }

    public function test_the_calendar_keeps_its_button_in_the_current_month(): void
    {
        $this->get(route('calendar'))
            ->assertOk()
            ->assertSee(__('app.calendar.today'))
            ->assertSee(self::HERE, escape: false);

        $this->get(route('calendar', ['monat' => '2026-07']))
            ->assertOk()
            ->assertSee(__('app.calendar.today'))
            ->assertDontSee(self::HERE, escape: false);
    }

    public function test_the_week_chart_keeps_its_button_too(): void
    {
        $this->get(route('dashboard'))->assertOk()->assertSee('is-current', escape: false);

        $this->get(route('dashboard', ['woche' => '2026-08-17']))
            ->assertOk()
            ->assertDontSee('is-current', escape: false);
    }
}
