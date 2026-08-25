<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EntryType;
use App\Models\TimeEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TimeEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->login();

        Carbon::setTestNow('2026-08-20 18:00:00');
    }

    public function test_an_entry_can_be_updated(): void
    {
        $entry = TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-20 09:00:00',
            'ended_at' => '2026-08-20 12:00:00',
        ]);

        $this->put(route('entries.update', $entry), [
            'type' => EntryType::Break->value,
            'date' => '2026-08-19',
            'starts_at' => '13:00',
            'ends_at' => '13:45',
            'note' => 'korrigiert',
        ])->assertRedirect(route('history', ['from' => '2026-08-17']));

        $entry->refresh();

        $this->assertSame(EntryType::Break, $entry->type);
        $this->assertSame('2026-08-19 13:00:00', $entry->started_at->toDateTimeString());
        $this->assertSame(2_700, $entry->durationInSeconds());
        $this->assertSame('korrigiert', $entry->note);
    }

    public function test_updating_an_entry_ignores_its_own_time_range(): void
    {
        $entry = TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-20 09:00:00',
            'ended_at' => '2026-08-20 12:00:00',
        ]);

        $this->put(route('entries.update', $entry), [
            'type' => EntryType::Work->value,
            'date' => '2026-08-20',
            'starts_at' => '09:00',
            'ends_at' => '13:00',
        ])->assertSessionHasNoErrors();

        $this->assertSame(14_400, $entry->refresh()->durationInSeconds());
    }

    public function test_an_entry_can_be_deleted(): void
    {
        $entry = TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-20 09:00:00',
            'ended_at' => '2026-08-20 12:00:00',
        ]);

        $this->delete(route('entries.destroy', $entry))->assertRedirect();

        $this->assertSame(0, TimeEntry::query()->count());
    }

    public function test_the_edit_page_renders(): void
    {
        $entry = TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-20 09:00:00',
            'ended_at' => '2026-08-20 12:00:00',
        ]);

        $this->get(route('entries.edit', $entry))
            ->assertOk()
            ->assertSee('09:00');
    }
}
