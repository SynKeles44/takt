<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EntryType;
use App\Enums\Widget;
use App\Models\TimeEntry;
use App\Services\TimeTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LastPatternTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->login();
        Carbon::setTestNow('2026-08-28 09:00:00');
    }

    private function entry(string $date, string $from, string $to, EntryType $type = EntryType::Work, ?string $note = null): void
    {
        TimeEntry::query()->create([
            'type' => $type,
            'started_at' => $date.' '.$from,
            'ended_at' => $date.' '.$to,
            'note' => $note,
        ]);
    }

    public function test_the_pattern_spans_the_whole_day_and_carries_the_first_break(): void
    {
        $this->entry('2026-08-27', '08:12', '12:30', note: 'Zeiterfassung');
        $this->entry('2026-08-27', '12:30', '13:05', EntryType::Break);
        $this->entry('2026-08-27', '13:05', '17:20');

        $pattern = app(TimeTracker::class)->lastPattern(Carbon::today());

        $this->assertSame('2026-08-27', $pattern['date']);
        $this->assertSame('08:12', $pattern['work_starts_at']);
        $this->assertSame('17:20', $pattern['work_ends_at']);
        $this->assertSame('12:30', $pattern['break_starts_at']);
        $this->assertSame('13:05', $pattern['break_ends_at']);
        $this->assertSame('Zeiterfassung', $pattern['note']);
    }

    public function test_the_day_being_booked_is_skipped(): void
    {
        $this->entry('2026-08-26', '09:00', '17:00');
        $this->entry('2026-08-28', '10:00', '11:00');

        $this->assertSame('2026-08-26', app(TimeTracker::class)->lastPattern(Carbon::today())['date']);
    }

    public function test_a_day_without_a_break_leaves_those_fields_out(): void
    {
        $this->entry('2026-08-27', '09:00', '17:00');

        $pattern = app(TimeTracker::class)->lastPattern(Carbon::today());

        $this->assertArrayNotHasKey('break_starts_at', $pattern);
        $this->assertArrayNotHasKey('note', $pattern);
    }

    public function test_nothing_booked_yet_means_no_pattern(): void
    {
        $this->assertNull(app(TimeTracker::class)->lastPattern(Carbon::today()));
    }

    public function test_a_running_entry_is_no_pattern(): void
    {
        TimeEntry::query()->create(['type' => EntryType::Work, 'started_at' => '2026-08-27 09:00:00']);

        $this->assertNull(app(TimeTracker::class)->lastPattern(Carbon::today()));
    }

    public function test_the_widget_offers_the_button_with_the_values(): void
    {
        $this->entry('2026-08-27', '08:12', '17:20');

        $this->get(route('dashboard.widget', ['widget' => Widget::Booking->value]))
            ->assertOk()
            ->assertSee(__('app.form.like_last_time'))
            ->assertSee('"work_starts_at":"08:12"', escape: false);
    }

    public function test_a_note_with_an_apostrophe_does_not_break_the_attribute(): void
    {
        $this->entry('2026-08-27', '08:00', '16:00', note: "Kund'innen-Termin");

        $response = $this->get(route('dashboard.widget', ['widget' => Widget::Booking->value]))->assertOk();

        // the value sits in a single-quoted attribute, so an apostrophe has to be escaped
        $response->assertSee('\u0027innen-Termin', escape: false);
        $response->assertDontSee("data-fill='{\"date\":\"2026-08-27\",\"work_starts_at\":\"08:00\",\"work_ends_at\":\"16:00\",\"note\":\"Kund'", escape: false);
    }
}
