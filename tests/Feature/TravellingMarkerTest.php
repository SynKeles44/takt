<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Widget;
use App\Models\DashboardWidget;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The highlight that travels to whatever you just picked.
 *
 * It was reported broken four times, and every one of those reports was about a row that simply
 * carried no hook — the JavaScript was fine and had nothing to attach to. So the hooks are asked
 * for here, per row: the marker element the script lifts out, and for the rows that navigate, the
 * key their position is handed over under. An edit that drops one turns this red instead of
 * quietly turning the row into a snap.
 */
class TravellingMarkerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Process::fake();
        Process::preventStrayProcesses();

        $this->login();

        Carbon::setTestNow('2026-08-25 10:00:00');
    }

    /**
     * Which of the three steps carries the marker, 1-based.
     *
     * Counted rather than matched, because the attribute that names the position is the component's
     * own claim about itself — this reads where the marker element actually sits.
     */
    private function markedStep(string $html): int
    {
        $head = Str::before(Str::after($html, 'data-marker-item="[data-date-step]"'), 'tab-marker');

        return substr_count($head, 'data-date-step');
    }

    public function test_the_sidebar_carries_the_marker_on_the_page_you_are_on(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('class="nav-list', escape: false)
            ->assertSee('nav-item nav-item-active', escape: false)
            ->assertSee('class="nav-marker"', escape: false);
    }

    public function test_both_development_rows_carry_their_own_marker(): void
    {
        Project::query()->create(['name' => 'Takt', 'path' => base_path(), 'position' => 0]);

        $this->get(route('packages'))
            ->assertOk()
            ->assertSee('data-tab-row', escape: false)
            ->assertSee('class="tab-marker"', escape: false)
            ->assertSee('data-subtab-row', escape: false)
            ->assertSee('class="subtab-marker"', escape: false);
    }

    public function test_the_insight_periods_carry_a_marker(): void
    {
        $this->get(route('insights'))
            ->assertOk()
            ->assertSee('data-period-row', escape: false)
            ->assertSee('class="tab-marker"', escape: false);
    }

    public function test_the_todo_filter_row_carries_a_marker_and_a_handover_key(): void
    {
        $this->get(route('todos.index'))
            ->assertOk()
            ->assertSee('data-marker-row', escape: false)
            ->assertSee('data-marker-key="todo-filter"', escape: false)
            ->assertSee('class="tab-marker"', escape: false);
    }

    public function test_the_week_chart_carries_the_fifth_date_navigator(): void
    {
        DashboardWidget::query()->create(['widget' => Widget::WeekChart, 'position' => 0]);
        auth()->user()->forceFill(['dashboard_arranged' => true])->save();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-marker-key="week-chart"', escape: false)
            ->assertSee('data-marker-at="now"', escape: false);
    }

    public function test_the_account_menu_marks_the_page_you_are_on(): void
    {
        $this->get(route('settings'))
            ->assertOk()
            ->assertSee('data-marker-key="account"', escape: false)
            ->assertSee('class="tab-marker"', escape: false);

        // on every other page nothing in that menu is current, and nothing is marked
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-marker-key="account"', escape: false)
            ->assertDontSee('nav-menu-item-active', escape: false);
    }

    /**
     * A date navigator marks where you stand relative to today, not what you last pressed: back in
     * the past, forward in the future, the middle button on the current period. Anything else is
     * wrong the moment a step forward out of the past lands in the past again.
     */
    public function test_the_date_navigators_mark_where_you_stand(): void
    {
        $cases = [
            'history' => [route('history'), route('history', ['from' => '2026-08-17']), route('history', ['from' => '2026-09-07'])],
            'calendar' => [route('calendar'), route('calendar', ['monat' => '2026-07']), route('calendar', ['monat' => '2026-10'])],
            'insights' => [route('insights'), route('insights', ['stand' => '2026-08-17']), route('insights', ['stand' => '2026-09-07'])],
            'dev' => [route('dev'), route('dev', ['tag' => '2026-08-17']), route('dev', ['tag' => '2026-09-07'])],
        ];

        foreach ($cases as $page => [$now, $past, $future]) {
            foreach (['now' => 2, 'past' => 1, 'future' => 3] as $position => $step) {
                $html = (string) $this->get(['now' => $now, 'past' => $past, 'future' => $future][$position])
                    ->assertOk()
                    ->getContent();

                $this->assertStringContainsString(
                    'data-marker-at="'.$position.'"',
                    $html,
                    "{$page} does not know it is in the {$position}",
                );

                $this->assertSame(
                    $step,
                    $this->markedStep($html),
                    "{$page} marks the wrong step in the {$position}",
                );
            }
        }
    }

    /**
     * Stepping forward out of the past can land in the past again, so the marker must not follow
     * the press — it waits for the page that actually arrives.
     */
    public function test_a_date_navigator_does_not_move_on_the_press(): void
    {
        $this->get(route('history'))
            ->assertOk()
            ->assertSee('data-marker-optimistic="false"', escape: false);
    }

    /**
     * The segmented controls on the tickets page are links, so each one is a page load — and a
     * marker that does not hand its position over arrives already in place, which is exactly the
     * snap the travelling highlight exists to remove.
     */
    public function test_both_ticket_segments_hand_their_position_to_the_next_page(): void
    {
        $this->get(route('tickets'))
            ->assertOk()
            ->assertSee('data-marker-key="tickets-view"', escape: false)
            ->assertSee('data-marker-key="tickets-window"', escape: false);
    }
}
