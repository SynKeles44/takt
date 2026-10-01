<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
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
