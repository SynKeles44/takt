<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DueState;
use App\Enums\TagColor;
use App\Models\Tag;
use App\Models\Todo;
use App\Models\User;
use App\Services\TodoMaintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TodoScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-24 12:00:00');

        $this->login();
    }

    private function tag(array $attributes = []): Tag
    {
        return Tag::query()->create(array_merge([
            'name' => 'Deadline',
            'color' => TagColor::Danger,
            'warn_lead_minutes' => 60,
            'auto_complete_expired' => false,
        ], $attributes));
    }

    public function test_a_todo_carries_title_body_due_date_and_tags(): void
    {
        $tag = $this->tag();

        $this->post(route('todos.store'), [
            'title' => 'Rechnung schreiben',
            'body' => "Positionen prüfen\nund senden",
            'due_date' => '2026-08-26',
            'due_time' => '14:30',
            'tags' => [$tag->id],
        ])->assertSessionHasNoErrors();

        $todo = Todo::query()->with('tags')->sole();

        $this->assertSame('Rechnung schreiben', $todo->title);
        $this->assertStringContainsString('Positionen prüfen', $todo->body);
        $this->assertSame('2026-08-26 14:30:00', $todo->due_at->toDateTimeString());
        $this->assertTrue($todo->due_has_time);
        $this->assertSame(['Deadline'], $todo->tags->pluck('name')->all());
    }

    public function test_a_date_without_a_time_ends_at_the_end_of_the_day(): void
    {
        $this->post(route('todos.store'), ['title' => 'Ohne Uhrzeit', 'due_date' => '2026-08-26']);

        $todo = Todo::query()->sole();

        $this->assertSame('2026-08-26 23:59:00', $todo->due_at->toDateTimeString());
        $this->assertFalse($todo->due_has_time);
    }

    public function test_a_time_without_a_date_is_rejected(): void
    {
        $this->post(route('todos.store'), ['title' => 'Nur Uhrzeit', 'due_time' => '10:00'])
            ->assertSessionHasErrors('due_date');

        $this->assertSame(0, Todo::query()->count());
    }

    public function test_the_due_state_follows_the_clock_and_the_tag_lead_time(): void
    {
        $tag = $this->tag(['warn_lead_minutes' => 120]);

        $cases = [
            '2026-08-24 11:00' => DueState::Overdue,
            '2026-08-24 13:00' => DueState::Warning,
            '2026-08-24 23:00' => DueState::Today,
            '2026-08-27 09:00' => DueState::Week,
            '2026-09-15 09:00' => DueState::Later,
        ];

        foreach ($cases as $due => $expected) {
            $todo = Todo::query()->create([
                'title' => 'Test '.$due,
                'due_at' => $due,
                'due_has_time' => true,
            ]);
            $todo->tags()->attach($tag);

            $this->assertSame($expected, $todo->fresh()->load('tags')->dueState(), "wrong state for {$due}");
        }

        $undated = Todo::query()->create(['title' => 'Ohne']);
        $this->assertSame(DueState::Undated, $undated->dueState());
    }

    public function test_a_todo_without_tags_has_no_warning_window(): void
    {
        $todo = Todo::query()->create(['title' => 'Ohne Tag', 'due_at' => '2026-08-24 13:00', 'due_has_time' => true]);

        $this->assertSame(DueState::Today, $todo->load('tags')->dueState());
    }

    public function test_expired_todos_are_auto_completed_only_for_opted_in_tags(): void
    {
        $auto = $this->tag(['name' => 'Auto', 'auto_complete_expired' => true]);
        $manual = $this->tag(['name' => 'Manuell', 'auto_complete_expired' => false]);

        $expiredAuto = Todo::query()->create(['title' => 'Läuft ab', 'due_at' => '2026-08-24 09:00', 'due_has_time' => true]);
        $expiredAuto->tags()->attach($auto);

        $expiredManual = Todo::query()->create(['title' => 'Bleibt offen', 'due_at' => '2026-08-24 09:00', 'due_has_time' => true]);
        $expiredManual->tags()->attach($manual);

        $future = Todo::query()->create(['title' => 'Später', 'due_at' => '2026-08-30 09:00', 'due_has_time' => true]);
        $future->tags()->attach($auto);

        $this->assertSame(1, app(TodoMaintenance::class)->run());

        $this->assertTrue($expiredAuto->refresh()->isDone());
        $this->assertSame('2026-08-24 09:00:00', $expiredAuto->completed_at->toDateTimeString());
        $this->assertFalse($expiredManual->refresh()->isDone());
        $this->assertFalse($future->refresh()->isDone());
    }

    public function test_opening_the_list_runs_the_maintenance(): void
    {
        $auto = $this->tag(['auto_complete_expired' => true]);
        $todo = Todo::query()->create(['title' => 'Abgelaufen', 'due_at' => '2026-08-24 08:00', 'due_has_time' => true]);
        $todo->tags()->attach($auto);

        $this->get(route('todos.index'))->assertOk();

        $this->assertTrue($todo->refresh()->isDone());
    }

    public function test_the_list_groups_by_due_state(): void
    {
        Todo::query()->create(['title' => 'Überfällige Sache', 'due_at' => '2026-08-23 09:00', 'due_has_time' => true]);
        Todo::query()->create(['title' => 'Heute noch', 'due_at' => '2026-08-24 20:00', 'due_has_time' => true]);
        Todo::query()->create(['title' => 'Irgendwann', 'due_at' => null]);

        $response = $this->get(route('todos.index'))->assertOk();
        $body = $response->getContent();

        $this->assertLessThan(strpos($body, 'Heute noch'), strpos($body, 'Überfällige Sache'));
        $this->assertLessThan(strpos($body, 'Irgendwann'), strpos($body, 'Heute noch'));
        $response->assertSee(DueState::Overdue->label())->assertSee(DueState::Undated->label());
    }

    public function test_urgent_todos_surface_on_the_dashboard(): void
    {
        $tag = $this->tag(['warn_lead_minutes' => 180]);

        $soon = Todo::query()->create(['title' => 'Gleich fällig', 'due_at' => '2026-08-24 13:30', 'due_has_time' => true]);
        $soon->tags()->attach($tag);

        Todo::query()->create(['title' => 'Ohne Termin']);

        $response = $this->get(route('dashboard'))->assertOk();
        $body = $response->getContent();

        $response->assertSee(__('app.todos.dashboard_title'))
            ->assertSee('Gleich fällig')
            ->assertSee(trans_choice('app.todos.urgent_count', 1));

        $this->assertLessThan(strpos($body, 'Ohne Termin'), strpos($body, 'Gleich fällig'));
    }

    public function test_a_todo_can_be_edited_with_all_fields(): void
    {
        $tag = $this->tag();
        $todo = Todo::query()->create(['title' => 'Alt', 'body' => 'alter Text']);

        $this->get(route('todos.edit', $todo))->assertOk()->assertSee('Alt');

        $this->put(route('todos.update', $todo), [
            'title' => 'Neu',
            'body' => 'neuer Text',
            'due_date' => '2026-08-28',
            'due_time' => '09:15',
            'tags' => [$tag->id],
        ])->assertRedirect(route('todos.show', $todo));

        $todo->refresh()->load('tags');

        $this->assertSame('Neu', $todo->title);
        $this->assertSame('neuer Text', $todo->body);
        $this->assertSame('2026-08-28 09:15:00', $todo->due_at->toDateTimeString());
        $this->assertSame([$tag->id], $todo->tags->modelKeys());
    }

    public function test_clearing_the_due_date_is_possible(): void
    {
        $todo = Todo::query()->create(['title' => 'Terminiert', 'due_at' => '2026-08-28 09:00', 'due_has_time' => true]);

        $this->put(route('todos.update', $todo), ['title' => 'Terminiert']);

        $this->assertNull($todo->refresh()->due_at);
    }

    public function test_a_foreign_tag_cannot_be_attached(): void
    {
        $foreignTag = Tag::query()->create([
            'user_id' => User::factory()->create()->getKey(),
            'name' => 'Fremd',
        ]);

        $this->post(route('todos.store'), ['title' => 'Versuch', 'tags' => [$foreignTag->id]])
            ->assertSessionHasErrors('tags.0');

        $this->assertSame(0, Todo::query()->count());
    }
}
