<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EntryType;
use App\Models\TimeEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DeleteDayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->login();

        Carbon::setTestNow('2026-08-20 20:00:00');
    }

    private function entry(string $date, string $from, string $to, EntryType $type = EntryType::Work): TimeEntry
    {
        return TimeEntry::query()->create([
            'type' => $type,
            'started_at' => $date.' '.$from,
            'ended_at' => $date.' '.$to,
        ]);
    }

    public function test_a_whole_day_is_deleted(): void
    {
        $this->entry('2026-08-19', '09:00:00', '12:00:00');
        $this->entry('2026-08-19', '12:00:00', '12:45:00', EntryType::Break);
        $this->entry('2026-08-19', '12:45:00', '17:00:00');
        $keeper = $this->entry('2026-08-18', '09:00:00', '17:00:00');

        $this->delete(route('days.destroy', '2026-08-19'))
            ->assertRedirect()
            ->assertSessionHas('status', '3 Buchungen vom 19. August 2026 gelöscht.');

        $this->assertSame(1, TimeEntry::query()->count());
        $this->assertNotNull(TimeEntry::query()->find($keeper->getKey()));
    }

    public function test_a_running_entry_on_that_day_is_deleted_too(): void
    {
        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-20 19:00:00',
        ]);

        $this->delete(route('days.destroy', '2026-08-20'))->assertRedirect();

        $this->assertSame(0, TimeEntry::query()->count());
    }

    public function test_deleting_an_empty_day_is_harmless(): void
    {
        $keeper = $this->entry('2026-08-18', '09:00:00', '17:00:00');

        $this->delete(route('days.destroy', '2026-08-19'))
            ->assertRedirect()
            ->assertSessionHas('status', 'Keine Buchungen an diesem Tag.');

        $this->assertNotNull(TimeEntry::query()->find($keeper->getKey()));
    }

    public function test_a_malformed_date_is_not_routed(): void
    {
        $this->delete('/tage/gestern')->assertNotFound();
    }

    public function test_an_impossible_date_is_rejected(): void
    {
        $this->delete('/tage/2026-13-45')->assertNotFound();
    }

    public function test_the_history_offers_the_action_for_days_with_entries(): void
    {
        $this->entry('2026-08-19', '09:00:00', '17:00:00');

        $this->get(route('history'))
            ->assertOk()
            ->assertSee(route('days.destroy', '2026-08-19'), escape: false)
            ->assertSee(__('app.history.delete_day'));
    }

    public function test_deleting_an_entry_from_its_edit_page_lands_on_the_week(): void
    {
        $entry = $this->entry('2026-08-19', '09:00:00', '17:00:00');

        $this->from(route('entries.edit', $entry))
            ->delete(route('entries.destroy', $entry))
            ->assertRedirect(route('history', ['from' => '2026-08-17']))
            ->assertSessionHas('undo');

        $this->get(route('entries.edit', $entry))->assertNotFound();
    }

    public function test_deleting_an_entry_from_a_list_stays_where_it_was(): void
    {
        $entry = $this->entry('2026-08-19', '09:00:00', '17:00:00');

        $this->from(route('history'))
            ->delete(route('entries.destroy', $entry))
            ->assertRedirect(route('history'));
    }
}
