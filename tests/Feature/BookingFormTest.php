<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EntryType;
use App\Models\TimeEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BookingFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->login();

        Carbon::setTestNow('2026-08-20 20:00:00');
    }

    private function book(array $payload): TestResponse
    {
        return $this->post(route('entries.store'), array_merge(['date' => '2026-08-20'], $payload));
    }

    private function shape(): array
    {
        return TimeEntry::query()
            ->orderBy('started_at')
            ->get()
            ->map(fn (TimeEntry $entry): string => sprintf(
                '%s %s-%s',
                $entry->type->value,
                $entry->started_at->format('H:i'),
                $entry->ended_at->format('H:i'),
            ))
            ->all();
    }

    public function test_only_work_is_booked_when_the_break_is_left_empty(): void
    {
        $this->book(['work_starts_at' => '09:00', 'work_ends_at' => '17:00'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['work 09:00-17:00'], $this->shape());
    }

    public function test_only_the_break_is_booked_when_work_is_left_empty(): void
    {
        $this->book(['break_starts_at' => '12:00', 'break_ends_at' => '12:45'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['break 12:00-12:45'], $this->shape());
    }

    public function test_both_are_booked_when_they_do_not_overlap(): void
    {
        $this->book([
            'work_starts_at' => '09:00',
            'work_ends_at' => '12:00',
            'break_starts_at' => '12:00',
            'break_ends_at' => '12:45',
        ])->assertSessionHasNoErrors();

        $this->assertSame(['work 09:00-12:00', 'break 12:00-12:45'], $this->shape());
    }

    public function test_a_break_inside_the_work_time_splits_the_work_time(): void
    {
        $this->book([
            'work_starts_at' => '09:00',
            'work_ends_at' => '17:00',
            'break_starts_at' => '12:00',
            'break_ends_at' => '12:45',
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            ['work 09:00-12:00', 'break 12:00-12:45', 'work 12:45-17:00'],
            $this->shape(),
        );
    }

    public function test_a_break_at_the_edge_of_the_work_time_keeps_one_work_entry(): void
    {
        $this->book([
            'work_starts_at' => '09:00',
            'work_ends_at' => '17:00',
            'break_starts_at' => '09:00',
            'break_ends_at' => '09:30',
        ])->assertSessionHasNoErrors();

        $this->assertSame(['break 09:00-09:30', 'work 09:30-17:00'], $this->shape());
    }

    public function test_the_note_lands_on_the_first_work_entry(): void
    {
        $this->book([
            'work_starts_at' => '09:00',
            'work_ends_at' => '17:00',
            'break_starts_at' => '12:00',
            'break_ends_at' => '12:45',
            'note' => 'Projekt Aurora',
        ]);

        $entries = TimeEntry::query()->orderBy('started_at')->get();

        $this->assertSame('Projekt Aurora', $entries[0]->note);
        $this->assertNull($entries[1]->note);
        $this->assertNull($entries[2]->note);
    }

    public function test_the_note_lands_on_the_break_when_only_a_break_is_booked(): void
    {
        $this->book(['break_starts_at' => '12:00', 'break_ends_at' => '12:45', 'note' => 'Mittag']);

        $this->assertSame('Mittag', TimeEntry::query()->sole()->note);
    }

    public function test_an_empty_form_is_rejected(): void
    {
        $this->book([])->assertSessionHasErrors('work_starts_at');

        $this->assertSame(0, TimeEntry::query()->count());
    }

    public function test_a_half_filled_block_is_rejected(): void
    {
        $this->book(['work_starts_at' => '09:00'])->assertSessionHasErrors('work_ends_at');
        $this->book(['break_ends_at' => '12:45'])->assertSessionHasErrors('break_starts_at');

        $this->assertSame(0, TimeEntry::query()->count());
    }

    public function test_a_break_that_only_partially_overlaps_the_work_time_is_rejected(): void
    {
        $this->book([
            'work_starts_at' => '09:00',
            'work_ends_at' => '17:00',
            'break_starts_at' => '08:00',
            'break_ends_at' => '10:00',
        ])->assertSessionHasErrors('break_starts_at');

        $this->assertSame(0, TimeEntry::query()->count());
    }

    public function test_a_break_that_swallows_the_work_time_is_rejected(): void
    {
        $this->book([
            'work_starts_at' => '09:00',
            'work_ends_at' => '17:00',
            'break_starts_at' => '09:00',
            'break_ends_at' => '17:00',
        ])->assertSessionHasErrors('break_starts_at');

        $this->assertSame(0, TimeEntry::query()->count());
    }

    public function test_an_overlap_with_an_existing_entry_is_rejected_and_nothing_is_written(): void
    {
        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-20 16:00:00',
            'ended_at' => '2026-08-20 18:00:00',
        ]);

        $this->book([
            'work_starts_at' => '09:00',
            'work_ends_at' => '17:00',
            'break_starts_at' => '12:00',
            'break_ends_at' => '12:45',
        ])->assertSessionHasErrors('date');

        $this->assertSame(1, TimeEntry::query()->count());
    }

    public function test_a_block_ending_after_midnight_rolls_over(): void
    {
        $this->book([
            'work_starts_at' => '22:00',
            'work_ends_at' => '02:00',
            'break_starts_at' => '23:30',
            'break_ends_at' => '00:00',
        ])->assertSessionHasNoErrors();

        $entries = TimeEntry::query()->orderBy('started_at')->get();

        $this->assertSame('2026-08-20 22:00:00', $entries[0]->started_at->toDateTimeString());
        $this->assertSame('2026-08-20 23:30:00', $entries[0]->ended_at->toDateTimeString());
        $this->assertSame('2026-08-21 00:00:00', $entries[1]->ended_at->toDateTimeString());
        $this->assertSame('2026-08-21 02:00:00', $entries[2]->ended_at->toDateTimeString());
    }

    public function test_identical_start_and_end_is_rejected(): void
    {
        $this->book(['work_starts_at' => '09:00', 'work_ends_at' => '09:00'])
            ->assertSessionHasErrors('work_ends_at');

        $this->book(['break_starts_at' => '12:00', 'break_ends_at' => '12:00'])
            ->assertSessionHasErrors('break_ends_at');

        $this->assertSame(0, TimeEntry::query()->count());
    }

    public function test_an_entry_touching_an_existing_one_is_allowed(): void
    {
        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-20 09:00:00',
            'ended_at' => '2026-08-20 12:00:00',
        ]);

        $this->book(['break_starts_at' => '12:00', 'break_ends_at' => '12:30'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['work 09:00-12:00', 'break 12:00-12:30'], $this->shape());
    }

    public function test_an_overlap_with_a_running_entry_is_rejected(): void
    {
        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-20 19:00:00',
        ]);

        $this->book(['work_starts_at' => '19:30', 'work_ends_at' => '19:45'])
            ->assertSessionHasErrors('date');

        $this->assertSame(1, TimeEntry::query()->count());
    }

    public function test_the_dashboard_renders_both_blocks(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('work_starts_at')
            ->assertSee('break_ends_at')
            ->assertSee(__('app.form.optional_hint'));
    }
}
