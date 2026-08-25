<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EntryType;
use App\Models\TimeEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class EditBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private TimeEntry $morning;

    private TimeEntry $lunch;

    private TimeEntry $afternoon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->login();

        Carbon::setTestNow('2026-08-20 20:00:00');

        $this->morning = $this->entry(EntryType::Work, '08:20', '14:00');
        $this->lunch = $this->entry(EntryType::Break, '14:00', '14:40');
        $this->afternoon = $this->entry(EntryType::Work, '14:40', '17:30');
    }

    private function entry(EntryType $type, string $from, string $to): TimeEntry
    {
        return TimeEntry::query()->create([
            'type' => $type,
            'started_at' => '2026-08-20 '.$from.':00',
            'ended_at' => '2026-08-20 '.$to.':00',
        ]);
    }

    private function retime(TimeEntry $entry, string $from, string $to): TestResponse
    {
        return $this->put(route('entries.update', $entry), [
            'type' => $entry->type->value,
            'date' => '2026-08-20',
            'starts_at' => $from,
            'ends_at' => $to,
        ]);
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

    public function test_stretching_a_break_pushes_the_following_work_start(): void
    {
        $this->retime($this->lunch, '14:00', '15:00')->assertSessionHasNoErrors();

        $this->assertSame(
            ['work 08:20-14:00', 'break 14:00-15:00', 'work 15:00-17:30'],
            $this->shape(),
        );
    }

    public function test_stretching_a_break_backwards_pulls_the_previous_work_end(): void
    {
        $this->retime($this->lunch, '13:30', '14:40')->assertSessionHasNoErrors();

        $this->assertSame(
            ['work 08:20-13:30', 'break 13:30-14:40', 'work 14:40-17:30'],
            $this->shape(),
        );
    }

    public function test_stretching_work_into_the_break_moves_the_break_start(): void
    {
        $this->retime($this->morning, '08:20', '14:10')->assertSessionHasNoErrors();

        $this->assertSame(
            ['work 08:20-14:10', 'break 14:10-14:40', 'work 14:40-17:30'],
            $this->shape(),
        );
    }

    public function test_stretching_across_both_neighbours_adjusts_both(): void
    {
        $this->retime($this->lunch, '13:30', '15:00')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Buchung aktualisiert, 2 angrenzende Buchungen angepasst.');

        $this->assertSame(
            ['work 08:20-13:30', 'break 13:30-15:00', 'work 15:00-17:30'],
            $this->shape(),
        );
    }

    public function test_shrinking_leaves_a_gap_and_never_grows_a_neighbour(): void
    {
        $this->retime($this->lunch, '14:10', '14:30')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', __('app.flash.updated'));

        $this->assertSame(
            ['work 08:20-14:00', 'break 14:10-14:30', 'work 14:40-17:30'],
            $this->shape(),
        );
    }

    public function test_swallowing_a_neighbour_is_refused(): void
    {
        $this->retime($this->lunch, '14:00', '17:30')->assertSessionHasErrors('starts_at');

        $this->assertSame(
            ['work 08:20-14:00', 'break 14:00-14:40', 'work 14:40-17:30'],
            $this->shape(),
        );
    }

    public function test_a_neighbour_may_be_trimmed_down_to_one_minute(): void
    {
        $this->retime($this->lunch, '14:00', '17:29')->assertSessionHasNoErrors();

        $this->assertSame(
            ['work 08:20-14:00', 'break 14:00-17:29', 'work 17:29-17:30'],
            $this->shape(),
        );
    }

    public function test_a_neighbour_is_not_trimmed_below_a_minute(): void
    {
        $this->afternoon->update(['ended_at' => '2026-08-20 17:30:30']);

        $this->retime($this->lunch, '14:00', '17:30')->assertSessionHasErrors('starts_at');

        $this->assertSame('14:40:00', $this->afternoon->refresh()->started_at->format('H:i:s'));
    }

    public function test_splitting_a_neighbour_is_refused(): void
    {
        $this->retime($this->lunch, '09:00', '10:00')->assertSessionHasErrors('starts_at');

        $this->assertSame('08:20', $this->morning->refresh()->started_at->format('H:i'));
        $this->assertSame('14:00', $this->morning->ended_at->format('H:i'));
    }

    public function test_a_running_neighbour_is_never_trimmed(): void
    {
        TimeEntry::query()->whereKey($this->afternoon->getKey())->delete();

        $running = TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-20 14:40:00',
        ]);

        $this->retime($this->lunch, '14:00', '15:00')->assertSessionHasErrors('starts_at');

        $this->assertTrue($running->refresh()->isRunning());
        $this->assertSame('14:40', $running->started_at->format('H:i'));
    }

    public function test_an_untouched_time_range_still_saves(): void
    {
        $this->put(route('entries.update', $this->lunch), [
            'type' => EntryType::Break->value,
            'date' => '2026-08-20',
            'starts_at' => '14:00',
            'ends_at' => '14:40',
            'note' => 'Mittag',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Mittag', $this->lunch->refresh()->note);
        $this->assertSame(
            ['work 08:20-14:00', 'break 14:00-14:40', 'work 14:40-17:30'],
            $this->shape(),
        );
    }

    public function test_the_edit_form_explains_the_behaviour(): void
    {
        $this->get(route('entries.edit', $this->lunch))
            ->assertOk()
            ->assertSee(__('app.form.neighbour_hint'));
    }
}
