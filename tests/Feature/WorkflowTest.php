<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DayNote;
use App\Models\StepTemplate;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->login();

        Carbon::setTestNow('2026-08-20 09:00:00');
    }

    public function test_a_task_can_be_snoozed_by_an_hour_a_day_or_a_week(): void
    {
        $todo = Todo::query()->create(['title' => 'Angebot', 'due_at' => '2026-08-20 10:00:00', 'due_has_time' => true]);

        $this->patch(route('todos.snooze', $todo), ['by' => 'hour'])->assertRedirect();
        $this->assertSame('2026-08-20 11:00:00', $todo->fresh()->due_at->toDateTimeString());

        $this->patch(route('todos.snooze', $todo), ['by' => 'tomorrow'])->assertRedirect();
        $this->assertSame('2026-08-21 11:00:00', $todo->fresh()->due_at->toDateTimeString());

        $this->patch(route('todos.snooze', $todo), ['by' => 'week'])->assertRedirect();
        $this->assertSame('2026-08-28 11:00:00', $todo->fresh()->due_at->toDateTimeString());
    }

    public function test_a_task_without_a_date_cannot_be_snoozed(): void
    {
        $todo = Todo::query()->create(['title' => 'Irgendwann']);

        $this->patch(route('todos.snooze', $todo), ['by' => 'week'])->assertRedirect();

        $this->assertNull($todo->fresh()->due_at);
    }

    public function test_a_checklist_template_can_be_created_and_applied(): void
    {
        $this->post(route('templates.store'), [
            'name' => 'Angebot',
            'items' => "Preise prüfen\nText schreiben\nVersenden",
        ])->assertRedirect();

        $template = StepTemplate::query()->firstOrFail();

        $this->assertCount(3, $template->items);

        $todo = Todo::query()->create(['title' => 'Neues Angebot']);

        $this->post(route('templates.apply', $todo), ['step_template_id' => $template->id])->assertRedirect();

        $this->assertSame(['Preise prüfen', 'Text schreiben', 'Versenden'], $todo->fresh()->steps->pluck('title')->all());
        $this->assertSame([1, 2, 3], $todo->fresh()->steps->pluck('position')->all());
    }

    public function test_a_checklist_can_be_attached_while_creating_a_task(): void
    {
        $this->post(route('templates.store'), [
            'name' => 'Angebot',
            'items' => "Preise prüfen\nText schreiben\nVersenden",
        ])->assertRedirect();

        $template = StepTemplate::query()->firstOrFail();

        $this->get(route('todos.index'))->assertOk()->assertSee('Checkliste')->assertSee('Angebot · 3 Schritte');

        $this->post(route('todos.store'), [
            'title' => 'Neues Angebot',
            'step_template_id' => $template->id,
        ])->assertRedirect();

        $todo = Todo::query()->where('title', 'Neues Angebot')->firstOrFail();

        $this->assertSame(['Preise prüfen', 'Text schreiben', 'Versenden'], $todo->steps->pluck('title')->all());
        $this->assertSame([1, 2, 3], $todo->steps->pluck('position')->all());
    }

    public function test_a_checklist_from_another_account_is_rejected_on_create(): void
    {
        $other = User::factory()->create();
        $foreign = StepTemplate::query()->forceCreate(['user_id' => $other->id, 'name' => 'Fremd']);

        $this->post(route('todos.store'), ['title' => 'Test', 'step_template_id' => $foreign->id])
            ->assertSessionHasErrors('step_template_id');

        $this->assertDatabaseCount('todos', 0);
    }

    public function test_a_task_opens_its_own_detail_view(): void
    {
        $todo = Todo::query()->create([
            'title' => 'Angebot Müller',
            'body' => 'Kontext zur Aufgabe',
            'due_at' => '2026-08-25 14:00:00',
            'due_has_time' => true,
        ]);
        $todo->steps()->create(['title' => 'Preise prüfen', 'position' => 1]);

        $this->get(route('todos.index'))
            ->assertOk()
            ->assertSee(route('todos.show', $todo), false);

        $this->get(route('todos.show', $todo))
            ->assertOk()
            ->assertSee('Angebot Müller')
            ->assertSee('Kontext zur Aufgabe')
            ->assertSee('Preise prüfen')
            ->assertSee(route('todos.edit', $todo), false)
            ->assertSee('Unterschritte');

        // the edit page is only the form now and returns to the task
        $this->get(route('todos.edit', $todo))
            ->assertOk()
            ->assertSee(route('todos.show', $todo), false)
            ->assertDontSee('Unterschritte');
    }

    public function test_deleting_from_the_task_page_lands_on_the_list_not_on_a_404(): void
    {
        $todo = Todo::query()->create(['title' => 'Weg damit']);

        $this->from(route('todos.show', $todo))
            ->delete(route('todos.destroy', $todo))
            ->assertRedirect(route('todos.index'))
            ->assertSessionHas('undo');

        $this->get(route('todos.show', $todo))->assertNotFound();
    }

    public function test_deleting_from_a_list_stays_where_it_was(): void
    {
        $todo = Todo::query()->create(['title' => 'Weg damit']);

        $this->from(route('dashboard'))
            ->delete(route('todos.destroy', $todo))
            ->assertRedirect(route('dashboard'));

        $second = Todo::query()->create(['title' => 'Auch weg']);

        $this->from(route('todos.index', ['filter' => 'all']))
            ->delete(route('todos.destroy', $second))
            ->assertRedirect(route('todos.index', ['filter' => 'all']));
    }

    public function test_a_template_can_be_built_from_an_existing_task(): void
    {
        $todo = Todo::query()->create(['title' => 'Monatsabschluss']);
        $todo->steps()->create(['title' => 'Belege sortieren', 'position' => 1]);

        $this->post(route('templates.from-todo', $todo), ['name' => 'Abschluss'])->assertRedirect();

        $this->assertSame(['Belege sortieren'], StepTemplate::query()->firstOrFail()->items->pluck('title')->all());
    }

    public function test_a_template_without_steps_is_refused(): void
    {
        $todo = Todo::query()->create(['title' => 'Leer']);

        $this->post(route('templates.from-todo', $todo), ['name' => 'Leer'])->assertStatus(422);
    }

    public function test_a_template_name_is_unique_per_account_only(): void
    {
        $other = User::factory()->create();
        StepTemplate::query()->forceCreate(['user_id' => $other->id, 'name' => 'Angebot']);

        $this->post(route('templates.store'), ['name' => 'Angebot', 'items' => 'Schritt'])->assertRedirect();
        $this->post(route('templates.store'), ['name' => 'Angebot', 'items' => 'Schritt'])->assertSessionHasErrors('name');
    }

    public function test_a_day_note_is_written_updated_and_cleared(): void
    {
        $this->post(route('notes.store'), ['day' => '2026-08-20', 'body' => 'Guter Tag'])->assertRedirect();
        $this->assertDatabaseHas('day_notes', ['day' => '2026-08-20 00:00:00', 'body' => 'Guter Tag']);

        $this->post(route('notes.store'), ['day' => '2026-08-20', 'body' => 'Sehr guter Tag'])->assertRedirect();
        $this->assertDatabaseCount('day_notes', 1);
        $this->assertSame('Sehr guter Tag', DayNote::query()->firstOrFail()->body);

        $this->get(route('dashboard'))->assertOk()->assertSee('Sehr guter Tag');

        $this->post(route('notes.store'), ['day' => '2026-08-20', 'body' => ''])->assertRedirect();
        $this->assertDatabaseCount('day_notes', 0);
    }

    public function test_toggling_a_task_over_xhr_answers_with_json_instead_of_a_redirect(): void
    {
        $todo = Todo::query()->create(['title' => 'Angebot']);

        $this->patchJson(route('todos.toggle', $todo))
            ->assertOk()
            ->assertJson(['done' => true, 'reload' => false])
            ->assertJsonPath('status', 'Aufgabe erledigt.');

        $this->patchJson(route('todos.toggle', $todo))
            ->assertOk()
            ->assertJson(['done' => false, 'reload' => false]);
    }

    public function test_a_recurring_task_asks_the_client_to_reload(): void
    {
        $todo = Todo::query()->create([
            'title' => 'Wochenbericht',
            'due_at' => '2026-08-20 09:00:00',
            'recurrence' => 'weekly',
        ]);

        $this->patchJson(route('todos.toggle', $todo))
            ->assertOk()
            ->assertJson(['reload' => true]);

        $this->assertDatabaseCount('todos', 2);
    }

    public function test_toggling_a_step_over_xhr_answers_with_json(): void
    {
        $todo = Todo::query()->create(['title' => 'Angebot']);
        $step = $todo->steps()->create(['title' => 'Preise prüfen', 'position' => 1]);

        $this->patchJson(route('steps.toggle', [$todo, $step]))
            ->assertOk()
            ->assertJson(['done' => true, 'reload' => false]);
    }

    public function test_a_toggle_without_xhr_still_redirects_back(): void
    {
        $todo = Todo::query()->create(['title' => 'Angebot']);

        $this->patch(route('todos.toggle', $todo))
            ->assertRedirect()
            ->assertSessionHas('status');
    }

    public function test_the_command_palette_offers_search_and_a_close_button(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-palette-close', false)
            ->assertSee('data-palette-search="'.route('search').'"', false)
            ->assertSee(route('insights'));
    }
}
