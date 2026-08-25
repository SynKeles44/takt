<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TagColor;
use App\Models\Tag;
use App\Models\Todo;
use App\Models\User;
use App\Services\TodoInputParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QuickCaptureTest extends TestCase
{
    use RefreshDatabase;

    private TodoInputParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-24 10:00:00');

        $this->login();
        $this->parser = app(TodoInputParser::class);
    }

    public function test_relative_days_are_understood(): void
    {
        $cases = [
            'Angebot heute' => '2026-08-24 23:59',
            'Angebot morgen' => '2026-08-25 23:59',
            'Angebot übermorgen' => '2026-08-26 23:59',
            'Angebot in 3 tagen' => '2026-08-27 23:59',
            'Angebot in einer woche' => '2026-08-31 23:59',
        ];

        foreach ($cases as $input => $expected) {
            $parsed = $this->parser->parse($input);

            $this->assertSame($expected, $parsed->dueAt->format('Y-m-d H:i'), $input);
            $this->assertSame('Angebot', $parsed->title, $input);
            $this->assertFalse($parsed->hasTime);
        }
    }

    public function test_times_and_dates_combine(): void
    {
        $parsed = $this->parser->parse('Rechnung senden morgen 14:30');

        $this->assertSame('2026-08-25 14:30', $parsed->dueAt->format('Y-m-d H:i'));
        $this->assertTrue($parsed->hasTime);
        $this->assertSame('Rechnung senden', $parsed->title);
    }

    public function test_a_bare_time_lands_today_or_tomorrow(): void
    {
        $later = $this->parser->parse('Kunde anrufen um 17 uhr');
        $this->assertSame('2026-08-24 17:00', $later->dueAt->format('Y-m-d H:i'));
        $this->assertSame('Kunde anrufen', $later->title);

        $passed = $this->parser->parse('Kunde anrufen um 8 uhr');
        $this->assertSame('2026-08-25 08:00', $passed->dueAt->format('Y-m-d H:i'));
    }

    public function test_explicit_dates_and_weekdays_are_understood(): void
    {
        $this->assertSame('2026-09-03 23:59', $this->parser->parse('Termin am 3.9.')->dueAt->format('Y-m-d H:i'));
        $this->assertSame('2027-01-07 23:59', $this->parser->parse('Termin am 7.1.2027')->dueAt->format('Y-m-d H:i'));

        $friday = $this->parser->parse('Bericht am freitag');
        $this->assertSame('2026-08-28 23:59', $friday->dueAt->format('Y-m-d H:i'));
        $this->assertSame('Bericht', $friday->title);
    }

    public function test_a_past_day_number_moves_into_the_next_year(): void
    {
        $this->assertSame('2027-01-05 23:59', $this->parser->parse('Steuer am 5.1.')->dueAt->format('Y-m-d H:i'));
    }

    public function test_tags_are_extracted(): void
    {
        $parsed = $this->parser->parse('Angebot #deadline #intern morgen');

        $this->assertSame(['deadline', 'intern'], $parsed->tags);
        $this->assertSame('Angebot', $parsed->title);
    }

    public function test_plain_text_stays_untouched(): void
    {
        $parsed = $this->parser->parse('Werkstatt aufräumen');

        $this->assertNull($parsed->dueAt);
        $this->assertSame([], $parsed->tags);
        $this->assertSame('Werkstatt aufräumen', $parsed->title);
    }

    public function test_a_title_that_is_only_a_date_keeps_the_original(): void
    {
        $parsed = $this->parser->parse('morgen');

        $this->assertSame('morgen', $parsed->title);
    }

    public function test_quick_capture_runs_through_the_form(): void
    {
        Tag::query()->create(['name' => 'Deadline', 'color' => TagColor::Danger]);

        $this->post(route('todos.store'), ['title' => 'Angebot Müller morgen 14:00 #deadline'])
            ->assertSessionHasNoErrors();

        $todo = Todo::query()->with('tags')->sole();

        $this->assertSame('Angebot Müller', $todo->title);
        $this->assertSame('2026-08-25 14:00:00', $todo->due_at->toDateTimeString());
        $this->assertTrue($todo->due_has_time);
        $this->assertSame(['Deadline'], $todo->tags->pluck('name')->all());
    }

    public function test_explicit_fields_beat_the_parsed_date(): void
    {
        $this->post(route('todos.store'), [
            'title' => 'Angebot morgen',
            'due_date' => '2026-09-01',
            'due_time' => '09:15',
        ])->assertSessionHasNoErrors();

        $todo = Todo::query()->sole();

        $this->assertSame('2026-09-01 09:15:00', $todo->due_at->toDateTimeString());
        $this->assertSame('Angebot', $todo->title);
    }

    public function test_an_unknown_tag_name_is_ignored(): void
    {
        $this->post(route('todos.store'), ['title' => 'Aufgabe #gibtsnicht'])->assertSessionHasNoErrors();

        $todo = Todo::query()->with('tags')->sole();

        $this->assertSame('Aufgabe', $todo->title);
        $this->assertCount(0, $todo->tags);
    }

    public function test_the_command_palette_is_present_with_its_actions(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-palette', escape: false)
            ->assertSee(__('app.palette.placeholder'))
            ->assertSee(__('app.timer.start_work'))
            ->assertSee(route('insights'), escape: false);
    }

    public function test_the_web_app_install_path_is_gone(): void
    {
        // Takt ships as a native app bundle, so the manifest, the service worker and
        // the offline page were removed — only the notification icon stays.
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<meta name="theme-color" content="#060911">', escape: false)
            ->assertDontSee('manifest', escape: false)
            ->assertDontSee('serviceWorker', escape: false)
            ->assertDontSee('apple-mobile-web-app', escape: false);

        $this->assertFileExists(public_path('icons/icon-192.png'));
        $this->assertFileDoesNotExist(public_path('manifest.webmanifest'));
        $this->assertFileDoesNotExist(public_path('sw.js'));
        $this->assertFileDoesNotExist(public_path('offline.html'));
    }

    public function test_the_due_watch_payload_only_lists_own_open_dated_tasks(): void
    {
        $tag = Tag::query()->create(['name' => 'Deadline', 'color' => TagColor::Danger, 'warn_lead_minutes' => 90]);
        $todo = Todo::query()->create(['title' => 'Mit Termin', 'due_at' => '2026-08-25 09:00', 'due_has_time' => true]);
        $todo->tags()->attach($tag);

        Todo::query()->create(['title' => 'Ohne Termin']);
        Todo::query()->create(['title' => 'Schon fertig', 'due_at' => '2026-08-25 09:00', 'completed_at' => now()]);
        Todo::query()->create([
            'user_id' => User::factory()->create()->getKey(),
            'title' => 'Fremd mit Termin',
            'due_at' => '2026-08-25 09:00',
        ]);

        $body = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-due-watch[^>]*>.*"title":"Mit Termin".*"lead":90/s', $body);
        $this->assertStringNotContainsString('"title":"Fremd mit Termin"', $body);
        $this->assertStringNotContainsString('"title":"Ohne Termin"', $body);
        $this->assertStringNotContainsString('"title":"Schon fertig"', $body);
    }
}
