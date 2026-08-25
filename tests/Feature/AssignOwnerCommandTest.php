<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EntryType;
use App\Models\TimeEntry;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignOwnerCommandTest extends TestCase
{
    use RefreshDatabase;

    private function orphan(): TimeEntry
    {
        return TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-20 09:00:00',
            'ended_at' => '2026-08-20 17:00:00',
        ]);
    }

    public function test_it_points_to_the_registration_when_no_account_exists(): void
    {
        $this->orphan();

        $this->artisan('takt:assign-owner', ['email' => 'niemand@example.test'])
            ->expectsOutputToContain('No account exists yet')
            ->assertFailed();

        $this->assertNull(TimeEntry::query()->withoutGlobalScope('owner')->sole()->user_id);
    }

    public function test_it_lists_the_existing_accounts_on_a_typo(): void
    {
        User::factory()->create(['email' => 'richtig@example.test']);
        $this->orphan();

        $this->artisan('takt:assign-owner', ['email' => 'falsch@example.test'])
            ->expectsOutputToContain('richtig@example.test')
            ->assertFailed();
    }

    public function test_it_adopts_ownerless_entries_and_todos(): void
    {
        $user = User::factory()->create(['email' => 'seymen@example.test']);
        $this->orphan();
        Todo::query()->create(['title' => 'Ohne Besitzer']);

        $this->artisan('takt:assign-owner', ['email' => 'seymen@example.test', '--force' => true])
            ->assertSuccessful();

        $this->assertSame($user->getKey(), TimeEntry::query()->withoutGlobalScope('owner')->sole()->user_id);
        $this->assertSame($user->getKey(), Todo::query()->withoutGlobalScope('owner')->sole()->user_id);
    }

    public function test_it_leaves_entries_that_already_have_an_owner_alone(): void
    {
        $owner = User::factory()->create();
        $newcomer = User::factory()->create(['email' => 'neu@example.test']);

        $entry = TimeEntry::query()->create([
            'user_id' => $owner->getKey(),
            'type' => EntryType::Work,
            'started_at' => '2026-08-20 09:00:00',
            'ended_at' => '2026-08-20 17:00:00',
        ]);

        $this->artisan('takt:assign-owner', ['email' => 'neu@example.test', '--force' => true])
            ->assertSuccessful();

        $this->assertSame($owner->getKey(), $entry->refresh()->user_id);
    }
}
