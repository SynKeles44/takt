<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EntryType;
use App\Models\TimeEntry;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReportAndRemindersTest extends TestCase
{
    use RefreshDatabase;

    private function work(string $day, string $from, string $to): void
    {
        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => $day.' '.$from,
            'ended_at' => $day.' '.$to,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-24 15:20:00');
    }

    public function test_the_report_renders_the_period_with_totals_and_tasks(): void
    {
        $this->login();

        $this->work('2026-08-24', '08:00:00', '16:30:00');
        Todo::query()->create(['title' => 'Angebot Müller', 'completed_at' => '2026-08-24 11:00:00']);

        $this->get(route('insights.report'))
            ->assertOk()
            ->assertSee('Bericht')
            ->assertSee('24. Aug – 30. Aug 2026')
            ->assertSee('8h 30m')
            ->assertSee('Angebot Müller')
            ->assertSee('window.print()', false);
    }

    public function test_the_report_follows_the_period_and_the_anchor(): void
    {
        $this->login();

        $this->work('2026-05-04', '09:00:00', '17:00:00');

        $this->get(route('insights.report', ['zeitraum' => 'monat', 'stand' => '2026-05-04']))
            ->assertOk()
            ->assertSee('Mai 2026')
            ->assertSee('8h 00m');

        $this->get(route('insights.report', ['zeitraum' => 'jahr', 'stand' => '2026-05-04']))
            ->assertOk()
            ->assertSee('2026');
    }

    public function test_the_report_only_ever_shows_own_entries(): void
    {
        $other = User::factory()->create();

        TimeEntry::query()->forceCreate([
            'user_id' => $other->id,
            'type' => EntryType::Work,
            'started_at' => '2026-08-24 08:00:00',
            'ended_at' => '2026-08-24 16:30:00',
        ]);

        $this->login();

        $this->get(route('insights.report'))->assertOk()->assertDontSee('8h 30m');
    }

    public function test_the_export_menu_lists_the_three_documents_on_the_month(): void
    {
        $this->login();

        $this->get(route('insights', ['zeitraum' => 'monat']))
            ->assertOk()
            ->assertSee('data-menu-toggle', false)
            ->assertSee('Export')
            ->assertSee('Bericht')
            ->assertSee('Stundenzettel')
            ->assertSee('CSV')
            ->assertSee('Monatsnachweis zum Ausdrucken, mit Zeilen für die Unterschriften.');

        // the week only offers the report; the timesheet hint is month-only
        // (the plain word "Stundenzettel" also lives in the command palette)
        $this->get(route('insights'))
            ->assertOk()
            ->assertSee('Bericht')
            ->assertDontSee('Monatsnachweis zum Ausdrucken, mit Zeilen für die Unterschriften.')
            ->assertDontSee('Alle Buchungen des Monats als CSV für Excel oder Numbers.');
    }

    public function test_the_worktime_watch_is_shipped_while_the_reminder_is_on(): void
    {
        $user = $this->login(['notify_worktime' => true, 'weekly_hours' => 40, 'working_days' => 5]);

        $this->work('2026-08-24', '08:00:00', '14:00:00');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-work-watch', false)
            ->assertSee('"target":28800', false)
            ->assertSee('"work":21600', false)
            ->assertSee('Tagesziel erreicht');

        $user->update(['notify_worktime' => false]);

        $this->get(route('dashboard'))->assertOk()->assertDontSee('data-work-watch', false);
    }

    public function test_tasks_no_longer_carry_booked_time(): void
    {
        $this->login();

        $todo = Todo::query()->create(['title' => 'Angebot']);

        $this->get(route('todos.edit', $todo))
            ->assertOk()
            ->assertDontSee('Zeit erfassen')
            ->assertDontSee('Erfasste Zeit');

        $this->assertFalse(Schema::hasColumn('time_entries', 'todo_id'));
        $this->assertFalse(app('router')->has('todos.timer'));
    }

    public function test_the_reminder_can_be_switched_in_the_settings(): void
    {
        $user = $this->login(['notify_worktime' => true]);

        $this->get(route('settings'))->assertOk()->assertSee('Erinnerungen zur Arbeitszeit');

        $this->put(route('settings.notifications'), [])->assertRedirect();
        $this->assertFalse($user->refresh()->notify_worktime);

        $this->put(route('settings.notifications'), ['notify_worktime' => '1'])->assertRedirect();
        $this->assertTrue($user->refresh()->notify_worktime);
    }
}
