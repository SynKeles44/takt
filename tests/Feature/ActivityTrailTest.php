<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EntryType;
use App\Enums\Widget;
use App\Models\ActivitySpan;
use App\Models\TimeEntry;
use App\Services\ActivityTrail;
use App\Services\TimeTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ActivityTrailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->login(['activity_trail' => true]);
        Carbon::setTestNow('2026-08-28 18:00:00');
    }

    private function span(string $app, string $from, string $to, ?string $title = null): array
    {
        return [
            'app' => $app,
            'title' => $title,
            'starts_at' => Carbon::today()->setTimeFromTimeString($from)->toIso8601String(),
            'ends_at' => Carbon::today()->setTimeFromTimeString($to)->toIso8601String(),
        ];
    }

    public function test_spans_are_recorded_and_grouped_per_application(): void
    {
        app(ActivityTrail::class)->record(auth()->user(), [
            $this->span('PhpStorm', '09:00', '10:30', 'galawork-web'),
            $this->span('Safari', '10:30', '10:45'),
            $this->span('PhpStorm', '10:45', '12:00', 'takt'),
        ]);

        $apps = app(ActivityTrail::class)->forDay(auth()->user(), Carbon::today());

        $this->assertSame('PhpStorm', $apps[0]['app']);
        $this->assertSame((90 + 75) * 60, $apps[0]['seconds']);
        $this->assertSame(['galawork-web', 'takt'], $apps[0]['titles']);
        $this->assertSame('Safari', $apps[1]['app']);
    }

    public function test_a_span_shorter_than_a_minute_is_noise(): void
    {
        $recorded = app(ActivityTrail::class)->record(auth()->user(), [
            $this->span('Finder', '09:00', '09:00'),
            ['app' => 'Mail', 'title' => null,
                'starts_at' => Carbon::today()->setTime(9, 0)->toIso8601String(),
                'ends_at' => Carbon::today()->setTime(9, 0, 30)->toIso8601String()],
        ]);

        $this->assertSame(0, $recorded);
    }

    public function test_nothing_is_recorded_while_the_trail_is_off(): void
    {
        auth()->user()->update(['activity_trail' => false]);

        $this->assertSame(0, app(ActivityTrail::class)->record(auth()->user(), [
            $this->span('PhpStorm', '09:00', '10:00'),
        ]));

        $this->assertSame(0, ActivitySpan::query()->count());
    }

    public function test_long_stretches_become_proposals_unless_already_booked(): void
    {
        app(ActivityTrail::class)->record(auth()->user(), [
            $this->span('PhpStorm', '09:00', '10:00', 'galawork-web'),
            $this->span('Slack', '10:00', '10:05'),
            $this->span('PhpStorm', '11:00', '12:00'),
        ]);

        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => Carbon::today()->setTime(9, 0),
            'ended_at' => Carbon::today()->setTime(10, 0),
        ]);

        $proposals = app(ActivityTrail::class)->proposals(auth()->user(), Carbon::today(), app(TimeTracker::class));

        // the 09:00 stretch is booked, the five-minute one is too short, one remains
        $this->assertCount(1, $proposals);
        $this->assertSame('11:00', $proposals[0]['from']->format('H:i'));
    }

    public function test_switching_it_off_deletes_what_was_recorded(): void
    {
        app(ActivityTrail::class)->record(auth()->user(), [$this->span('PhpStorm', '09:00', '10:00')]);

        $this->put(route('trail.update'), ['activity_trail' => 0])->assertRedirect();

        $this->assertSame(0, ActivitySpan::query()->count());
        $this->assertFalse(auth()->user()->fresh()->activity_trail);
    }

    public function test_retention_drops_what_is_older(): void
    {
        auth()->user()->update(['activity_retention_days' => 7]);

        ActivitySpan::query()->create([
            'app' => 'Alt',
            'started_at' => Carbon::today()->subDays(20),
            'ended_at' => Carbon::today()->subDays(20)->addHour(),
        ]);
        ActivitySpan::query()->create([
            'app' => 'Neu',
            'started_at' => Carbon::today()->subDays(2),
            'ended_at' => Carbon::today()->subDays(2)->addHour(),
        ]);

        app(ActivityTrail::class)->prune(auth()->user());

        $this->assertSame(['Neu'], ActivitySpan::query()->pluck('app')->all());
    }

    public function test_the_shell_reports_through_the_page(): void
    {
        $this->postJson(route('trail.store'), ['spans' => [$this->span('PhpStorm', '09:00', '10:00')]])
            ->assertOk()
            ->assertJson(['recorded' => 1, 'enabled' => true]);
    }

    public function test_the_widget_shows_shares_and_proposals(): void
    {
        app(ActivityTrail::class)->record(auth()->user(), [$this->span('PhpStorm', '09:00', '10:00', 'takt')]);

        $this->get(route('dashboard.widget', ['widget' => Widget::Activity->value]))
            ->assertOk()
            ->assertSee('PhpStorm')
            ->assertSee(__('app.trail.proposals'))
            ->assertSee(__('app.trail.book'));
    }

    public function test_the_widget_points_at_the_setting_while_it_is_off(): void
    {
        auth()->user()->update(['activity_trail' => false]);

        $this->get(route('dashboard.widget', ['widget' => Widget::Activity->value]))
            ->assertOk()
            ->assertSee(__('app.trail.empty'))
            ->assertSee(__('app.trail.title'));
    }
}
