<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EntryType;
use App\Models\TimeEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TimerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->login();
    }

    public function test_starting_work_creates_a_running_entry(): void
    {
        Carbon::setTestNow('2026-08-20 09:00:00');

        $this->post(route('timer.start'), ['type' => EntryType::Work->value])
            ->assertRedirect();

        $entry = TimeEntry::query()->sole();

        $this->assertSame(EntryType::Work, $entry->type);
        $this->assertTrue($entry->isRunning());
        $this->assertSame('2026-08-20 09:00:00', $entry->started_at->toDateTimeString());
    }

    public function test_starting_a_break_closes_the_running_work_entry(): void
    {
        Carbon::setTestNow('2026-08-20 09:00:00');
        $this->post(route('timer.start'), ['type' => EntryType::Work->value]);

        Carbon::setTestNow('2026-08-20 12:30:00');
        $this->post(route('timer.start'), ['type' => EntryType::Break->value]);

        $work = TimeEntry::query()->ofType(EntryType::Work)->sole();
        $break = TimeEntry::query()->ofType(EntryType::Break)->sole();

        $this->assertSame('2026-08-20 12:30:00', $work->ended_at->toDateTimeString());
        $this->assertTrue($break->isRunning());
        $this->assertSame(12_600, $work->durationInSeconds());
    }

    public function test_ending_a_break_and_returning_to_work_starts_a_new_work_entry(): void
    {
        Carbon::setTestNow('2026-08-20 12:00:00');
        $this->post(route('timer.start'), ['type' => EntryType::Break->value]);

        Carbon::setTestNow('2026-08-20 12:30:00');
        $this->post(route('timer.start'), ['type' => EntryType::Work->value]);

        $this->assertSame(2, TimeEntry::query()->count());
        $this->assertSame(EntryType::Work, TimeEntry::query()->running()->sole()->type);
    }

    public function test_starting_the_same_type_twice_does_not_create_a_second_entry(): void
    {
        Carbon::setTestNow('2026-08-20 09:00:00');
        $this->post(route('timer.start'), ['type' => EntryType::Work->value]);

        Carbon::setTestNow('2026-08-20 09:05:00');
        $this->post(route('timer.start'), ['type' => EntryType::Work->value]);

        $this->assertSame(1, TimeEntry::query()->count());
        $this->assertSame('2026-08-20 09:00:00', TimeEntry::query()->sole()->started_at->toDateTimeString());
    }

    public function test_stopping_closes_the_running_entry(): void
    {
        Carbon::setTestNow('2026-08-20 09:00:00');
        $this->post(route('timer.start'), ['type' => EntryType::Work->value]);

        Carbon::setTestNow('2026-08-20 17:00:00');
        $this->post(route('timer.stop'))->assertRedirect();

        $entry = TimeEntry::query()->sole();

        $this->assertFalse($entry->isRunning());
        $this->assertSame(28_800, $entry->durationInSeconds());
    }

    public function test_stopping_without_a_running_timer_is_a_no_op(): void
    {
        $this->post(route('timer.stop'))
            ->assertRedirect()
            ->assertSessionHas('status', __('app.flash.nothing_running'));

        $this->assertSame(0, TimeEntry::query()->count());
    }

    public function test_an_unknown_type_is_rejected(): void
    {
        $this->post(route('timer.start'), ['type' => 'nap'])
            ->assertSessionHasErrors('type');

        $this->assertSame(0, TimeEntry::query()->count());
    }
}
