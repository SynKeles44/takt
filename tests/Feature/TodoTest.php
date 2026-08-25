<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Todo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TodoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-24 18:00:00');

        $this->login();
    }

    public function test_the_page_renders_with_an_empty_state(): void
    {
        $this->get(route('todos.index'))
            ->assertOk()
            ->assertSee(__('app.todos.placeholder'))
            ->assertSee(__('app.todos.empty'));
    }

    public function test_a_task_is_added(): void
    {
        $this->post(route('todos.store'), ['title' => '  Zeiterfassung testen  '])
            ->assertRedirect()
            ->assertSessionHas('status', __('app.flash.todo_created'));

        $todo = Todo::query()->sole();

        $this->assertSame('Zeiterfassung testen', $todo->title);
        $this->assertSame(1, $todo->position);
        $this->assertFalse($todo->isDone());
    }

    public function test_tasks_keep_their_insertion_order(): void
    {
        foreach (['Eins', 'Zwei', 'Drei'] as $title) {
            $this->post(route('todos.store'), ['title' => $title]);
        }

        $this->assertSame([1, 2, 3], Todo::query()->inOrder()->pluck('position')->all());
        $this->assertSame(['Eins', 'Zwei', 'Drei'], Todo::query()->inOrder()->pluck('title')->all());
    }

    public function test_an_empty_or_too_long_title_is_rejected(): void
    {
        $this->post(route('todos.store'), ['title' => '   '])->assertSessionHasErrors('title');
        $this->post(route('todos.store'), ['title' => str_repeat('a', 201)])->assertSessionHasErrors('title');

        $this->assertSame(0, Todo::query()->count());
    }

    public function test_a_task_is_ticked_off_and_reopened(): void
    {
        $todo = Todo::query()->create(['title' => 'Erledige mich']);

        $this->patch(route('todos.toggle', $todo))
            ->assertRedirect()
            ->assertSessionHas('status', __('app.flash.todo_done'));

        $this->assertSame('2026-08-24 18:00:00', $todo->refresh()->completed_at->toDateTimeString());

        $this->patch(route('todos.toggle', $todo))
            ->assertSessionHas('status', __('app.flash.todo_reopened'));

        $this->assertNull($todo->refresh()->completed_at);
    }

    public function test_a_task_is_renamed(): void
    {
        $todo = Todo::query()->create(['title' => 'Alt']);

        $this->put(route('todos.update', $todo), ['title' => 'Neu formuliert'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Neu formuliert', $todo->refresh()->title);
    }

    public function test_a_task_is_deleted(): void
    {
        $todo = Todo::query()->create(['title' => 'Weg damit']);

        $this->delete(route('todos.destroy', $todo))->assertRedirect();

        $this->assertSame(0, Todo::query()->count());
    }

    public function test_completed_tasks_are_cleared_at_once(): void
    {
        Todo::query()->create(['title' => 'Offen']);
        Todo::query()->create(['title' => 'Fertig 1', 'completed_at' => now()]);
        Todo::query()->create(['title' => 'Fertig 2', 'completed_at' => now()]);

        $this->delete(route('todos.clear'))
            ->assertRedirect()
            ->assertSessionHas('status', '2 erledigte Aufgaben gelöscht.');

        $this->assertSame(['Offen'], Todo::query()->pluck('title')->all());
    }

    public function test_the_filters_show_the_right_slice(): void
    {
        Todo::query()->create(['title' => 'Noch offen']);
        Todo::query()->create(['title' => 'Schon fertig', 'completed_at' => now()]);

        $this->get(route('todos.index'))
            ->assertSee('Noch offen')
            ->assertDontSee('Schon fertig');

        $this->get(route('todos.index', ['filter' => 'done']))
            ->assertSee('Schon fertig')
            ->assertDontSee('Noch offen');

        $this->get(route('todos.index', ['filter' => 'all']))
            ->assertSee('Noch offen')
            ->assertSee('Schon fertig');
    }

    public function test_an_unknown_filter_falls_back_to_open(): void
    {
        Todo::query()->create(['title' => 'Noch offen']);
        Todo::query()->create(['title' => 'Schon fertig', 'completed_at' => now()]);

        $this->get(route('todos.index', ['filter' => 'quatsch']))
            ->assertOk()
            ->assertSee('Noch offen')
            ->assertDontSee('Schon fertig');
    }

    public function test_done_tasks_sink_to_the_bottom_in_the_all_view(): void
    {
        $open = Todo::query()->create(['title' => 'Offen', 'position' => 5]);
        $done = Todo::query()->create(['title' => 'Fertig', 'position' => 1, 'completed_at' => now()]);

        $response = $this->get(route('todos.index', ['filter' => 'all']))->assertOk();

        $body = $response->getContent();

        $this->assertLessThan(strpos($body, $done->title), strpos($body, $open->title));
    }

    public function test_the_navigation_offers_the_todo_page(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('todos.index'), escape: false)
            ->assertSee(__('app.nav.todos'));
    }
}
