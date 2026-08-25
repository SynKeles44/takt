<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EntryType;
use App\Models\TimeEntry;
use App\Models\Todo;
use App\Models\User;
use App\Services\Trash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TrashTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->login();

        Carbon::setTestNow('2026-08-20 18:00:00');
    }

    private function entry(): TimeEntry
    {
        return TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-19 09:00:00',
            'ended_at' => '2026-08-19 17:00:00',
        ]);
    }

    public function test_a_deleted_entry_lands_in_the_trash_with_an_undo_offer(): void
    {
        $entry = $this->entry();

        $this->delete(route('entries.destroy', $entry))
            ->assertRedirect()
            ->assertSessionHas('undo');

        $this->assertSoftDeleted($entry);

        $this->get(route('trash'))->assertOk()->assertSee('Mi, 19. Aug');
    }

    public function test_an_entry_can_be_restored_from_the_trash(): void
    {
        $entry = $this->entry();
        $entry->delete();

        $this->patch(route('trash.entry.restore', $entry))->assertRedirect();

        $this->assertNotSoftDeleted($entry->fresh());
    }

    public function test_a_task_can_be_restored_and_purged(): void
    {
        $todo = Todo::query()->create(['title' => 'Angebot senden']);
        $todo->delete();

        $this->patch(route('trash.todo.restore', $todo))->assertRedirect();
        $this->assertNotSoftDeleted($todo->fresh());

        $todo->delete();
        $this->delete(route('trash.todo.purge', $todo))->assertRedirect();
        $this->assertDatabaseCount('todos', 0);
    }

    public function test_emptying_the_trash_removes_everything_for_good(): void
    {
        $this->entry()->delete();
        Todo::query()->create(['title' => 'Weg damit'])->delete();

        $this->delete(route('trash.empty'))->assertRedirect();

        $this->assertDatabaseCount('time_entries', 0);
        $this->assertDatabaseCount('todos', 0);
    }

    public function test_records_older_than_the_retention_window_are_purged_on_view(): void
    {
        $fresh = $this->entry();
        $fresh->delete();

        $old = TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-06-01 09:00:00',
            'ended_at' => '2026-06-01 17:00:00',
        ]);
        $old->delete();
        $old->forceFill(['deleted_at' => Carbon::now()->subDays(Trash::KEEP_DAYS + 1)])->saveQuietly();

        $this->get(route('trash'))->assertOk();

        $this->assertDatabaseCount('time_entries', 1);
        $this->assertSoftDeleted($fresh);
    }

    public function test_the_trash_of_another_account_stays_out_of_reach(): void
    {
        $other = User::factory()->create();

        $todo = Todo::query()->forceCreate(['user_id' => $other->id, 'title' => 'Fremd']);
        $todo->delete();

        $this->get(route('trash'))->assertOk()->assertDontSee('Fremd');
        $this->patch(route('trash.todo.restore', $todo))->assertNotFound();
        $this->delete(route('trash.todo.purge', $todo))->assertNotFound();
    }
}
