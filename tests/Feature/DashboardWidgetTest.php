<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Widget;
use App\Models\DashboardWidget;
use App\Models\User;
use App\Services\Dashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DashboardWidgetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->login();
    }

    /** @param  array<int, Widget>  $widgets */
    private function arrange(array $widgets, int $span = 4, int $rows = 3): TestResponse
    {
        return $this->putJson(route('dashboard.arrange'), [
            'widgets' => array_map(
                fn (Widget $widget): array => ['widget' => $widget->value, 'span' => $span, 'rows' => $rows],
                $widgets,
            ),
        ]);
    }

    public function test_an_untouched_dashboard_shows_the_default_widgets(): void
    {
        $this->assertSame(0, DashboardWidget::query()->count());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('app.timer.idle_title'))
            ->assertSee(__('app.stats.work_today'))
            ->assertSee(__('app.chart.title'))
            ->assertSee(__('app.notes.title'))
            ->assertSee(__('app.entries.today_title'));
    }

    public function test_the_whole_layout_is_stored_in_one_go(): void
    {
        $this->arrange([Widget::MonthSummary, Widget::Timer])->assertOk()->assertJson(['saved' => true]);

        $this->assertSame(
            ['month_summary', 'timer'],
            DashboardWidget::query()->inOrder()->pluck('widget')->map(fn (Widget $w): string => $w->value)->all(),
        );

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('app.widget.month_summary.days'))
            ->assertSee('--widget-span: 4', escape: false)
            ->assertSee('--widget-rows: 3', escape: false);
    }

    public function test_a_widget_left_out_of_the_layout_disappears(): void
    {
        $this->arrange([Widget::Timer]);

        $this->get(route('dashboard'))->assertOk()->assertDontSee(__('app.notes.placeholder'));
    }

    public function test_the_order_of_the_layout_is_the_order_on_the_board(): void
    {
        $this->arrange([Widget::Stats, Widget::Timer]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['data-widget="stats"', 'data-widget="timer"'], escape: false);
    }

    public function test_an_empty_layout_leaves_an_empty_board(): void
    {
        $this->putJson(route('dashboard.arrange'), ['widgets' => []])->assertOk();

        $this->get(route('dashboard'))->assertOk()->assertSee(__('app.widget.empty_dashboard'));
    }

    public function test_a_widget_cannot_be_placed_twice(): void
    {
        $this->arrange([Widget::Todos, Widget::Todos])->assertJsonValidationErrors('widgets.1.widget');
    }

    public function test_an_unknown_widget_is_refused(): void
    {
        $this->putJson(route('dashboard.arrange'), [
            'widgets' => [['widget' => 'nonsense', 'span' => 4, 'rows' => 3]],
        ])->assertJsonValidationErrors('widgets.0.widget');
    }

    public function test_a_size_outside_the_grid_is_refused(): void
    {
        $this->arrange([Widget::Timer], span: 7)->assertJsonValidationErrors('widgets.0.span');
        $this->arrange([Widget::Timer], rows: 9)->assertJsonValidationErrors('widgets.0.rows');
    }

    public function test_reset_brings_the_default_layout_back(): void
    {
        $this->arrange([Widget::Snippets]);

        $this->post(route('dashboard.reset'))->assertRedirect()->assertSessionHas('status');

        $this->assertSame(
            array_map(fn (Widget $widget): string => $widget->value, Widget::defaults()),
            DashboardWidget::query()->inOrder()->pluck('widget')->map(fn (Widget $w): string => $w->value)->all(),
        );
    }

    public function test_the_drawer_offers_exactly_what_is_not_on_the_board(): void
    {
        $this->arrange([Widget::Timer]);

        $response = $this->get(route('dashboard'))->assertOk();

        foreach (Widget::cases() as $widget) {
            if ($widget === Widget::Timer) {
                $response->assertDontSee('data-add-widget="timer"', escape: false);

                continue;
            }

            $response->assertSee('data-add-widget="'.$widget->value.'"', escape: false);
        }

        $response->assertSee(__('app.widget.drawer_title'));
    }

    public function test_the_board_carries_the_edit_affordances(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-board-toggle', escape: false)
            ->assertSee('data-widget-remove', escape: false)
            ->assertSee('data-widget-grip', escape: false)
            ->assertSee('data-board-drawer', escape: false)
            ->assertSee(route('dashboard.arrange'), escape: false);
    }

    public function test_every_widget_renders_on_its_own(): void
    {
        foreach (Widget::cases() as $widget) {
            $this->arrange([$widget])->assertOk();

            $this->get(route('dashboard'))->assertOk();
        }
    }

    public function test_another_users_layout_stays_untouched(): void
    {
        $other = User::factory()->create();

        // user_id is not fillable on purpose — set it explicitly to plant a foreign row
        $foreign = new DashboardWidget(['widget' => Widget::Snippets, 'position' => 0]);
        $foreign->user_id = $other->getKey();
        $foreign->save();

        $this->assertSame(
            array_map(fn (Widget $widget): string => $widget->value, Widget::defaults()),
            app(Dashboard::class)->layout($this->user)
                ->pluck('widget')
                ->map(fn (Widget $widget): string => $widget->value)
                ->all(),
        );

        $this->arrange([Widget::Timer]);

        $this->assertDatabaseHas('dashboard_widgets', [
            'user_id' => $other->getKey(),
            'widget' => Widget::Snippets->value,
        ]);
    }

    public function test_the_layout_needs_a_login(): void
    {
        auth()->logout();

        $this->putJson(route('dashboard.arrange'), ['widgets' => []])->assertUnauthorized();
    }
}
