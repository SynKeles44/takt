<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EntryType;
use App\Enums\TagColor;
use App\Models\Tag;
use App\Models\TimeEntry;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CalendarExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-24 12:00:00');

        $this->user = $this->login(['name' => 'Seymen Keles']);

        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-24 09:00:00',
            'ended_at' => '2026-08-24 17:30:00',
            'note' => 'Baustelle Nord',
        ]);

        TimeEntry::query()->create([
            'type' => EntryType::Break,
            'started_at' => '2026-08-24 12:00:00',
            'ended_at' => '2026-08-24 12:30:00',
        ]);
    }

    public function test_the_calendar_shows_the_month_with_work_and_tasks(): void
    {
        Todo::query()->create(['title' => 'Rechnung senden', 'due_at' => '2026-08-26 10:00', 'due_has_time' => true]);

        $this->get(route('calendar'))
            ->assertOk()
            ->assertSee('August 2026')
            ->assertSee('Rechnung senden')
            ->assertSee('8.5');
    }

    public function test_the_calendar_can_be_paged_and_validates_the_month(): void
    {
        $this->get(route('calendar', ['monat' => '2026-07']))->assertOk()->assertSee('Juli 2026');
        $this->get(route('calendar', ['monat' => 'irgendwann']))->assertSessionHasErrors('monat');
    }

    public function test_the_feed_serves_dated_tasks_with_an_alarm(): void
    {
        $tag = Tag::query()->create(['name' => 'Deadline', 'color' => TagColor::Danger, 'warn_lead_minutes' => 120]);
        $todo = Todo::query()->create(['title' => 'Angebot; mit Komma, Text', 'due_at' => '2026-08-28 14:00', 'due_has_time' => true]);
        $todo->tags()->attach($tag);

        Todo::query()->create(['title' => 'Ohne Termin']);

        $body = $this->get(route('calendar.feed', ['token' => $this->user->icalToken()]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->getContent();

        $this->assertStringContainsString('BEGIN:VCALENDAR', $body);
        $this->assertStringContainsString('SUMMARY:Angebot\; mit Komma\, Text', $body);
        $this->assertStringContainsString('DTSTART:20260828T120000Z', $body);
        $this->assertStringContainsString('TRIGGER:-PT120M', $body);
        $this->assertStringContainsString('CATEGORIES:Deadline', $body);
        $this->assertStringNotContainsString('Ohne Termin', $body);
    }

    public function test_an_all_day_task_uses_a_date_value(): void
    {
        Todo::query()->create(['title' => 'Ganztägig', 'due_at' => '2026-08-30 23:59', 'due_has_time' => false]);

        $body = $this->get(route('calendar.feed', ['token' => $this->user->icalToken()]))->getContent();

        $this->assertStringContainsString('DTSTART;VALUE=DATE:20260830', $body);
    }

    public function test_the_feed_never_leaks_another_users_tasks(): void
    {
        $other = User::factory()->create();
        Todo::query()->create(['user_id' => $other->getKey(), 'title' => 'Fremde Aufgabe', 'due_at' => '2026-08-27 09:00']);
        Todo::query()->create(['title' => 'Meine Aufgabe', 'due_at' => '2026-08-27 09:00']);

        $body = $this->get(route('calendar.feed', ['token' => $this->user->icalToken()]))->getContent();

        $this->assertStringContainsString('Meine Aufgabe', $body);
        $this->assertStringNotContainsString('Fremde Aufgabe', $body);
    }

    public function test_an_unknown_or_regenerated_token_stops_working(): void
    {
        $token = $this->user->icalToken();

        $this->get('/kalender/'.str_repeat('x', 48).'.ics')->assertNotFound();

        $this->put(route('settings.ical'))->assertRedirect();

        $this->assertNotSame($token, $this->user->refresh()->ical_token);
        $this->get(route('calendar.feed', ['token' => $token]))->assertNotFound();
    }

    public function test_the_feed_needs_no_login(): void
    {
        $token = $this->user->icalToken();

        $this->post(route('logout'));

        $this->get(route('calendar.feed', ['token' => $token]))->assertOk();
    }

    public function test_the_month_page_sums_the_month(): void
    {
        $this->get(route('insights', ['zeitraum' => 'monat']))
            ->assertOk()
            ->assertSee('August 2026')
            ->assertSee('8h 30m')
            ->assertSee('30m')
            ->assertSee('+30m');
    }

    public function test_the_csv_export_is_excel_friendly(): void
    {
        $response = $this->get(route('month.csv', ['monat' => '2026-08']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        $body = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('Datum;Art;Von;Bis;Dauer;Dezimal;Notiz', $body);
        $this->assertStringContainsString('24.08.2026;Arbeitszeit;09:00;17:30;"8h 30m";8,5;"Baustelle Nord"', $body);
        $this->assertStringContainsString('24.08.2026;Pause;12:00;12:30;30m;0,5;', $body);
    }

    public function test_the_timesheet_renders_for_printing(): void
    {
        $this->get(route('month.timesheet', ['monat' => '2026-08']))
            ->assertOk()
            ->assertSee(__('app.month.timesheet'))
            ->assertSee('Seymen Keles')
            ->assertSee(__('app.month.sign_employee'))
            ->assertDontSee('nav-item', escape: false);
    }

    public function test_the_backup_contains_only_own_data(): void
    {
        $other = User::factory()->create();
        TimeEntry::query()->create([
            'user_id' => $other->getKey(),
            'type' => EntryType::Work,
            'started_at' => '2026-08-20 09:00:00',
            'ended_at' => '2026-08-20 10:00:00',
            'note' => 'Fremde Buchung',
        ]);

        $tag = Tag::query()->create(['name' => 'Intern', 'color' => TagColor::Accent]);
        $todo = Todo::query()->create(['title' => 'Meine Aufgabe']);
        $todo->tags()->attach($tag);
        $todo->steps()->create(['title' => 'Schritt', 'position' => 1]);

        $response = $this->get(route('backup'))->assertOk();
        $payload = $response->json();

        $this->assertSame('Takt', $payload['app']);
        $this->assertSame('Seymen Keles', $payload['user']['name']);
        $this->assertCount(2, $payload['time_entries']);
        $this->assertSame(['Intern'], array_column($payload['tags'], 'name'));
        $this->assertSame('Meine Aufgabe', $payload['todos'][0]['title']);
        $this->assertSame(['Schritt'], array_column($payload['todos'][0]['steps'], 'title'));
        $this->assertStringNotContainsString('Fremde Buchung', $response->getContent());
    }
}
