<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AbsenceType;
use App\Enums\EntryType;
use App\Enums\Widget;
use App\Models\Absence;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\TimeTracker;
use App\Services\WorkCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HomeOfficeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->login();

        Carbon::setTestNow('2026-08-26 10:00:00');
    }

    private function homeOffice(string $from, ?string $to = null): Absence
    {
        return Absence::query()->create([
            'type' => AbsenceType::HomeOffice,
            'starts_on' => $from,
            'ends_on' => $to ?? $from,
        ]);
    }

    private function workDay(string $date, string $from, string $to): void
    {
        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => $date.' '.$from,
            'ended_at' => $date.' '.$to,
        ]);
    }

    public function test_a_home_office_day_keeps_its_target(): void
    {
        $this->workDay('2026-08-25', '09:00', '17:00');
        $this->homeOffice('2026-08-25');

        $calendar = app(WorkCalendar::class);
        $exempt = $calendar->exemptDatesForBalance($this->user);

        $this->assertNotContains('2026-08-25', $exempt);
        $this->assertSame(0, app(TimeTracker::class)->balance(28_800, null, $exempt)['seconds']);
    }

    public function test_a_vacation_day_still_loses_its_target(): void
    {
        $this->workDay('2026-08-25', '09:00', '17:00');

        Absence::query()->create([
            'type' => AbsenceType::Vacation,
            'starts_on' => '2026-08-25',
            'ends_on' => '2026-08-25',
        ]);

        $this->assertContains('2026-08-25', app(WorkCalendar::class)->exemptDatesForBalance($this->user));
    }

    public function test_home_office_never_hides_a_holiday(): void
    {
        $this->homeOffice('2026-05-01');

        $calendar = app(WorkCalendar::class);
        $from = Carbon::parse('2026-05-01');

        $this->assertContains('2026-05-01', $calendar->exemptDatesForBalance($this->user, Carbon::parse('2026-08-26')));
        $this->assertTrue($calendar->exemptions($this->user, $from, $from)['2026-05-01']['blocking']);
    }

    public function test_weekends_do_not_count_as_home_office_days(): void
    {
        $this->homeOffice('2026-08-24', '2026-08-30');

        $this->assertSame(
            ['2026-08-24', '2026-08-25', '2026-08-26', '2026-08-27', '2026-08-28'],
            app(WorkCalendar::class)->homeOfficeDates(Carbon::parse('2026-08-24'), Carbon::parse('2026-08-30')),
        );
    }

    public function test_the_summary_reports_year_window_and_average(): void
    {
        $this->homeOffice('2026-03-02', '2026-03-06');
        $this->homeOffice('2026-08-24', '2026-08-25');

        $this->user->forceFill(['home_office_days' => 2])->save();

        $summary = app(WorkCalendar::class)->homeOfficeSummary($this->user, 14);

        $this->assertSame(7, $summary['days_year']);
        $this->assertSame(2, $summary['days_window']);
        $this->assertSame(1.0, $summary['per_week']);
        $this->assertSame(2, $summary['target']);
    }

    public function test_the_absence_page_shows_the_home_office_card(): void
    {
        $this->homeOffice('2026-08-24', '2026-08-25');

        $this->get(route('absences'))
            ->assertOk()
            ->assertSee(__('app.absence.home_office_title'))
            ->assertSee(__('app.absence.home_office_hint'));
    }

    public function test_the_widget_renders_and_remembers_the_window(): void
    {
        $this->homeOffice('2026-08-24', '2026-08-25');

        $this->get(route('dashboard.widget', ['widget' => Widget::HomeOffice->value]))
            ->assertOk()
            ->assertSee(__('app.widget.home_office.label'))
            ->assertSee(__('app.widget.home_office.window_30'));

        $this->post(route('dashboard.home-office'), ['window' => 7])->assertRedirect();

        $this->assertSame(7, $this->user->fresh()->home_office_window);
    }

    public function test_an_unknown_window_is_rejected(): void
    {
        $this->post(route('dashboard.home-office'), ['window' => 99])->assertSessionHasErrors('window');
    }

    public function test_the_absence_form_offers_home_office_as_a_type(): void
    {
        $this->get(route('absences'))
            ->assertOk()
            ->assertSee('value="home_office"', escape: false)
            ->assertSee(AbsenceType::HomeOffice->label());
    }

    public function test_the_calendar_marks_a_home_office_day_without_calling_it_free(): void
    {
        $this->homeOffice('2026-08-26');

        $this->get(route('calendar'))
            ->assertOk()
            ->assertSee('bg-rest', escape: false);

        $this->get(route('dashboard.widget', ['widget' => 'timer']))
            ->assertOk()
            ->assertSee(__('app.absence.today_marker', ['label' => AbsenceType::HomeOffice->label()]))
            ->assertDontSee(__('app.absence.today', ['label' => AbsenceType::HomeOffice->label()]));
    }

    public function test_the_settings_store_the_agreed_days(): void
    {
        $this->put(route('settings.worktime'), [
            'weekly_hours' => '40',
            'working_days' => 5,
            'home_office_days' => 3,
        ])->assertRedirect();

        $this->assertSame(3, $this->user->fresh()->home_office_days);
    }
}
