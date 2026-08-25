<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EntryType;
use App\Models\TimeEntry;
use App\Models\Todo;
use App\Models\User;
use App\Services\TimeTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class IsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $other;

    private TimeEntry $foreignEntry;

    private Todo $foreignTodo;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-24 18:00:00');

        $this->other = User::factory()->create(['name' => 'Fremde Person']);

        $this->foreignEntry = TimeEntry::query()->create([
            'user_id' => $this->other->getKey(),
            'type' => EntryType::Work,
            'started_at' => '2026-08-24 09:00:00',
            'ended_at' => '2026-08-24 17:00:00',
            'note' => 'Fremde Buchung',
        ]);

        $this->foreignTodo = Todo::query()->create([
            'user_id' => $this->other->getKey(),
            'title' => 'Fremde Aufgabe',
        ]);

        $this->login(['name' => 'Ich']);
    }

    public function test_another_users_entries_are_invisible(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Fremde Buchung')
            ->assertSee('0m');

        $this->get(route('history'))->assertOk()->assertDontSee('Fremde Buchung');
    }

    public function test_another_users_entry_cannot_be_opened(): void
    {
        $this->get(route('entries.edit', $this->foreignEntry))->assertNotFound();
    }

    public function test_another_users_entry_cannot_be_changed(): void
    {
        $this->put(route('entries.update', $this->foreignEntry), [
            'type' => EntryType::Break->value,
            'date' => '2026-08-24',
            'starts_at' => '10:00',
            'ends_at' => '11:00',
        ])->assertNotFound();

        $this->assertSame('Fremde Buchung', $this->foreignEntry->refresh()->note);
    }

    public function test_another_users_entry_cannot_be_deleted(): void
    {
        $this->delete(route('entries.destroy', $this->foreignEntry))->assertNotFound();

        $this->assertNotNull(TimeEntry::query()->withoutGlobalScope('owner')->find($this->foreignEntry->getKey()));
    }

    public function test_deleting_a_day_never_touches_another_users_entries(): void
    {
        $this->delete(route('days.destroy', '2026-08-24'))->assertRedirect();

        $this->assertNotNull(TimeEntry::query()->withoutGlobalScope('owner')->find($this->foreignEntry->getKey()));
    }

    public function test_a_new_entry_belongs_to_the_signed_in_user(): void
    {
        $this->post(route('entries.store'), [
            'date' => '2026-08-24',
            'work_starts_at' => '08:00',
            'work_ends_at' => '08:30',
        ])->assertSessionHasNoErrors();

        $entry = TimeEntry::query()->sole();

        $this->assertSame(auth()->id(), $entry->user_id);
    }

    public function test_the_timer_starts_for_the_signed_in_user(): void
    {
        $this->post(route('timer.start'), ['type' => EntryType::Work->value]);

        $this->assertSame(auth()->id(), TimeEntry::query()->running()->sole()->user_id);
    }

    public function test_another_users_todos_are_invisible_and_untouchable(): void
    {
        $this->get(route('todos.index'))->assertOk()->assertDontSee('Fremde Aufgabe');

        $this->patch(route('todos.toggle', $this->foreignTodo))->assertNotFound();
        $this->put(route('todos.update', $this->foreignTodo), ['title' => 'gekapert'])->assertNotFound();
        $this->delete(route('todos.destroy', $this->foreignTodo))->assertNotFound();

        $this->assertSame('Fremde Aufgabe', $this->foreignTodo->refresh()->title);
    }

    public function test_clearing_completed_todos_stays_within_the_own_account(): void
    {
        Todo::query()->withoutGlobalScope('owner')->whereKey($this->foreignTodo->getKey())->update(['completed_at' => now()]);

        Todo::query()->create(['title' => 'Meine erledigte', 'completed_at' => now()]);

        $this->delete(route('todos.clear'))->assertRedirect();

        $this->assertNotNull(Todo::query()->withoutGlobalScope('owner')->find($this->foreignTodo->getKey()));
        $this->assertSame(0, Todo::query()->count());
    }

    public function test_the_balance_only_counts_own_entries(): void
    {
        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-21 09:00:00',
            'ended_at' => '2026-08-21 18:00:00',
        ]);

        $balance = app(TimeTracker::class)->balance(28_800);

        $this->assertSame(3_600, $balance['seconds']);
        $this->assertSame(1, $balance['days']);
    }
}
