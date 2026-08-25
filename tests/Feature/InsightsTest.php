<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EntryType;
use App\Models\DayNote;
use App\Models\TimeEntry;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InsightsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->login();

        Carbon::setTestNow('2026-08-20 18:00:00');
    }

    private function work(string $day, string $from, string $to): void
    {
        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => $day.' '.$from,
            'ended_at' => $day.' '.$to,
        ]);
    }

    public function test_the_week_view_sums_work_target_and_balance(): void
    {
        $this->work('2026-08-17', '09:00:00', '17:00:00');
        $this->work('2026-08-18', '09:00:00', '15:00:00');
        $this->work('2026-08-11', '09:00:00', '13:00:00');

        $this->get(route('insights'))
            ->assertOk()
            ->assertSee('17. Aug – 23. Aug 2026')
            ->assertSee('14h 00m')
            ->assertSee('16h 00m')
            ->assertSee("\u{2212}2h 00m", false)
            ->assertSee('2 Buchungstage');
    }

    public function test_the_week_view_lists_tasks_completed_in_that_week(): void
    {
        Todo::query()->create(['title' => 'Abrechnung geprüft', 'completed_at' => '2026-08-18 11:00:00']);
        Todo::query()->create(['title' => 'Alte Aufgabe', 'completed_at' => '2026-08-05 11:00:00']);
        Todo::query()->create(['title' => 'Noch offen']);

        $this->get(route('insights'))
            ->assertOk()
            ->assertSee('Abrechnung geprüft')
            ->assertDontSee('Alte Aufgabe')
            ->assertDontSee('Noch offen');
    }

    public function test_the_week_view_can_be_paged_back(): void
    {
        $this->work('2026-08-11', '09:00:00', '13:00:00');

        $this->get(route('insights', ['stand' => '2026-08-10']))
            ->assertOk()
            ->assertSee('10. Aug – 16. Aug 2026')
            ->assertSee('4h 00m');
    }

    public function test_the_year_view_shows_a_bar_per_month_and_the_heatmap(): void
    {
        $this->work('2026-03-04', '08:00:00', '16:30:00');

        $this->get(route('insights', ['zeitraum' => 'jahr']))
            ->assertOk()
            ->assertSee('2026')
            ->assertSee('8h 30m')
            ->assertSee('Mär')
            ->assertSee('Aktivität')
            ->assertSee('Mi, 4. Mär 2026 · 8h 30m');
    }

    public function test_every_period_can_be_paged_and_keeps_the_same_layout(): void
    {
        $this->work('2025-05-06', '09:00:00', '17:00:00');

        foreach (['woche', 'monat', 'jahr'] as $period) {
            $this->get(route('insights', ['zeitraum' => $period, 'stand' => '2025-05-06']))
                ->assertOk()
                ->assertSee('Verteilung')
                ->assertSee('Erledigte Aufgaben')
                ->assertSee('8h 00m');

            $this->get(route('insights', ['zeitraum' => $period]))
                ->assertOk()
                ->assertSee('Verteilung')
                ->assertSee('0 Buchungstage');
        }
    }

    public function test_an_unknown_period_falls_back_to_the_week(): void
    {
        $this->get(route('insights', ['zeitraum' => 'quartal']))->assertSessionHasErrors('zeitraum');
        $this->get(route('insights'))->assertOk()->assertSee('17. Aug – 23. Aug 2026');
    }

    public function test_the_month_period_offers_the_timesheet_and_csv(): void
    {
        $this->get(route('insights', ['zeitraum' => 'monat']))
            ->assertOk()
            ->assertSee(route('month.timesheet', ['monat' => '2026-08']), false)
            ->assertSee(route('month.csv', ['monat' => '2026-08']), false);
    }

    public function test_only_the_year_carries_the_activity_heatmap(): void
    {
        $this->work('2026-08-18', '09:00:00', '17:00:00');

        $this->get(route('insights', ['zeitraum' => 'jahr']))
            ->assertOk()
            ->assertSee('Aktivität')
            ->assertSee('weniger')
            ->assertSee('Di, 18. Aug 2026 · 8h 00m');

        foreach (['woche', 'monat'] as $period) {
            $this->get(route('insights', ['zeitraum' => $period]))
                ->assertOk()
                ->assertDontSee('Aktivität')
                ->assertSee('Verteilung');
        }
    }

    public function test_the_header_keeps_the_same_controls_in_every_period(): void
    {
        foreach (['woche', 'monat', 'jahr'] as $period) {
            $response = $this->get(route('insights', ['zeitraum' => $period]))->assertOk();

            $response->assertSee('Heute')->assertSee('Woche')->assertSee('Monat')->assertSee('Jahr');
        }

        $this->get(route('insights', ['zeitraum' => 'woche']))
            ->assertDontSee(route('month.csv', ['monat' => '2026-08']), false);
    }

    public function test_the_search_finds_tasks_notes_and_entry_texts(): void
    {
        Todo::query()->create(['title' => 'Angebot schreiben', 'body' => 'für Musterfirma']);
        DayNote::query()->create(['day' => '2026-08-18', 'body' => 'Angebot mit dem Team besprochen']);

        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-18 09:00:00',
            'ended_at' => '2026-08-18 12:00:00',
            'note' => 'Angebot kalkuliert',
        ]);

        $response = $this->getJson(route('search', ['q' => 'angebot']))->assertOk();

        $labels = collect($response->json('results'))->pluck('label');

        $this->assertContains('Angebot schreiben', $labels);
        $this->assertContains('Angebot kalkuliert', $labels);
        $this->assertContains('Angebot mit dem Team besprochen', $labels);
    }

    public function test_the_search_ignores_short_terms(): void
    {
        Todo::query()->create(['title' => 'Angebot schreiben']);

        $this->getJson(route('search', ['q' => 'a']))->assertOk()->assertExactJson(['results' => []]);
    }

    public function test_the_search_only_returns_own_records(): void
    {
        $other = User::factory()->create();

        Todo::query()->forceCreate(['user_id' => $other->id, 'title' => 'Fremdes Angebot']);

        $this->getJson(route('search', ['q' => 'angebot']))->assertOk()->assertExactJson(['results' => []]);
    }
}
