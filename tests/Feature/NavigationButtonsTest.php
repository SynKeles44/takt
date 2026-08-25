<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * A "back to now" button that only appears when it has something to do moves every button
 * next to it. It stays in place and goes inactive instead.
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

    public function test_the_history_keeps_its_button_in_the_current_week(): void
    {
        $this->get(route('history'))
            ->assertOk()
            ->assertSee(__('app.week.current'))
            ->assertSee('is-current', escape: false);

        $this->get(route('history', ['from' => '2026-08-17']))
            ->assertOk()
            ->assertSee(__('app.week.current'))
            ->assertDontSee('is-current', escape: false);
    }

    public function test_the_calendar_keeps_its_button_in_the_current_month(): void
    {
        $this->get(route('calendar'))
            ->assertOk()
            ->assertSee(__('app.calendar.today'))
            ->assertSee('is-current', escape: false);

        $this->get(route('calendar', ['monat' => '2026-07']))
            ->assertOk()
            ->assertSee(__('app.calendar.today'))
            ->assertDontSee('is-current', escape: false);
    }

    public function test_the_week_chart_keeps_its_button_too(): void
    {
        $this->get(route('dashboard'))->assertOk()->assertSee('is-current', escape: false);

        $this->get(route('dashboard', ['woche' => '2026-08-17']))
            ->assertOk()
            ->assertDontSee('is-current', escape: false);
    }
}
