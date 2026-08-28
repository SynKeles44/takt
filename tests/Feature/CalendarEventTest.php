<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Widget;
use App\Services\CalendarEvents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CalendarEventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->login();
        Carbon::setTestNow('2026-08-28 09:00:00');
    }

    private function event(string $from, string $to, string $title = 'Daily'): array
    {
        return [
            'title' => $title,
            'starts_at' => Carbon::today()->setTimeFromTimeString($from)->toIso8601String(),
            'ends_at' => Carbon::today()->setTimeFromTimeString($to)->toIso8601String(),
            'calendar' => 'Arbeit',
        ];
    }

    public function test_events_are_kept_for_their_day_and_sorted(): void
    {
        $this->postJson(route('calendar.events'), [
            'day' => Carbon::today()->toDateString(),
            'events' => [$this->event('14:00', '14:30', 'Refinement'), $this->event('09:30', '09:45')],
        ])->assertOk()->assertJson(['stored' => 2]);

        $events = app(CalendarEvents::class)->forDay(auth()->user(), Carbon::today());

        $this->assertSame(['Daily', 'Refinement'], array_column($events, 'title'));
        $this->assertSame(15 * 60, $events[0]['seconds']);
        $this->assertSame('Arbeit', $events[0]['calendar']);
    }

    public function test_an_event_of_another_day_is_dropped(): void
    {
        $this->postJson(route('calendar.events'), [
            'day' => Carbon::today()->toDateString(),
            'events' => [[
                'title' => 'Gestern',
                'starts_at' => Carbon::yesterday()->setTime(10, 0)->toIso8601String(),
                'ends_at' => Carbon::yesterday()->setTime(11, 0)->toIso8601String(),
            ]],
        ])->assertOk()->assertJson(['stored' => 0]);
    }

    public function test_an_event_ending_before_it_starts_is_dropped(): void
    {
        $this->postJson(route('calendar.events'), [
            'day' => Carbon::today()->toDateString(),
            'events' => [$this->event('11:00', '10:00')],
        ])->assertOk()->assertJson(['stored' => 0]);
    }

    public function test_the_widget_lists_them_with_a_booking_button(): void
    {
        app(CalendarEvents::class)->store(auth()->user(), Carbon::today(), [$this->event('09:30', '10:15')]);

        $this->get(route('dashboard.widget', ['widget' => Widget::Meetings->value]))
            ->assertOk()
            ->assertSee('Daily')
            ->assertSee('09:30')
            ->assertSee(__('app.widget.meetings.book'))
            ->assertSee('value="10:15"', escape: false);
    }

    public function test_without_a_report_the_widget_says_so(): void
    {
        $this->get(route('dashboard.widget', ['widget' => Widget::Meetings->value]))
            ->assertOk()
            ->assertSee(__('app.widget.meetings.empty'));
    }

    public function test_a_flood_of_events_is_rejected(): void
    {
        $this->postJson(route('calendar.events'), [
            'day' => Carbon::today()->toDateString(),
            'events' => array_fill(0, 120, $this->event('09:00', '09:15')),
        ])->assertStatus(422);
    }
}
