<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AbsenceType;
use App\Models\Absence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Marking days happens in the browser; what the server owes it is the markup it reads
 * and an endpoint that takes the marked range.
 */
class CalendarRangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->login();

        Carbon::setTestNow('2026-08-25 10:00:00');
    }

    public function test_every_day_of_the_grid_carries_its_date(): void
    {
        $response = $this->get(route('calendar'))->assertOk();

        $response->assertSee('data-day-picker', escape: false);

        foreach (['2026-08-01', '2026-08-15', '2026-08-31'] as $date) {
            $response->assertSee('data-day="'.$date.'"', escape: false);
        }
    }

    public function test_the_day_cells_refuse_the_browsers_own_link_drag(): void
    {
        // without this the browser answers press-and-move with a link drag and no marking happens
        $this->get(route('calendar'))->assertOk()->assertSee('draggable="false"', escape: false);
    }

    public function test_the_absence_window_is_part_of_the_calendar(): void
    {
        $this->get(route('calendar'))
            ->assertOk()
            ->assertSee('data-absence-dialog', escape: false)
            ->assertSee('data-absence-start', escape: false)
            ->assertSee('data-absence-end', escape: false)
            ->assertSee(route('absences.store'), escape: false)
            ->assertSee(AbsenceType::Vacation->label())
            ->assertSee(__('app.absence.select_hint'));
    }

    public function test_a_marked_range_is_booked_and_shows_up_in_the_calendar(): void
    {
        $this->from(route('calendar'))->post(route('absences.store'), [
            'type' => AbsenceType::Vacation->value,
            'starts_on' => '2026-08-10',
            'ends_on' => '2026-08-14',
            'note' => 'Nordsee',
        ])->assertRedirect(route('calendar'))->assertSessionHas('status');

        $absence = Absence::query()->sole();

        $this->assertSame(AbsenceType::Vacation, $absence->type);
        $this->assertSame('2026-08-10', $absence->starts_on->toDateString());
        $this->assertSame('2026-08-14', $absence->ends_on->toDateString());
        $this->assertSame('Nordsee', $absence->note);

        $this->get(route('calendar'))->assertOk()->assertSee(AbsenceType::Vacation->label());
    }

    public function test_a_single_marked_day_is_enough(): void
    {
        $this->from(route('calendar'))->post(route('absences.store'), [
            'type' => AbsenceType::Sick->value,
            'starts_on' => '2026-08-18',
            'ends_on' => '2026-08-18',
        ])->assertRedirect(route('calendar'));

        $absence = Absence::query()->sole();

        $this->assertTrue($absence->starts_on->isSameDay($absence->ends_on));
        $this->assertSame('2026-08-18', $absence->starts_on->toDateString());
    }

    public function test_a_range_that_ends_before_it_starts_is_refused(): void
    {
        $this->from(route('calendar'))->post(route('absences.store'), [
            'type' => AbsenceType::Vacation->value,
            'starts_on' => '2026-08-20',
            'ends_on' => '2026-08-12',
        ])->assertSessionHasErrors('ends_on');

        $this->assertDatabaseCount('absences', 0);
    }
}
