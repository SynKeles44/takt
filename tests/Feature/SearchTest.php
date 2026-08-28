<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AbsenceType;
use App\Enums\EntryType;
use App\Models\Absence;
use App\Models\DayNote;
use App\Models\Project;
use App\Models\Snippet;
use App\Models\StepTemplate;
use App\Models\Tag;
use App\Models\TimeEntry;
use App\Models\Todo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The palette used to see four sources out of a dozen. Every group it can return is asserted
 * here once, so a new area cannot silently stay unsearchable.
 */
class SearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->login(['github_token' => 'ghp_test']);
    }

    private function groupsFor(string $term): array
    {
        $results = $this->get(route('search', ['q' => $term]))->assertOk()->json('results');

        return array_values(array_unique(array_column($results, 'group')));
    }

    public function test_a_single_letter_returns_nothing(): void
    {
        $this->get(route('search', ['q' => 'a']))->assertOk()->assertExactJson(['results' => []]);
    }

    public function test_projects_are_found_by_name_and_by_path(): void
    {
        Project::query()->create(['name' => 'Zeitkasten', 'path' => '/Users/dev/Projekte/zeitkasten-api']);

        $this->assertContains(__('app.dev.projects'), $this->groupsFor('zeitkast'));
        $this->assertContains(__('app.dev.projects'), $this->groupsFor('projekte/zeit'));
    }

    public function test_absences_are_found_by_their_note(): void
    {
        Absence::query()->create([
            'type' => AbsenceType::Vacation,
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-14',
            'note' => 'Nordsee',
        ]);

        $this->assertContains(__('app.absence.title'), $this->groupsFor('nordsee'));
    }

    public function test_tags_and_templates_are_found(): void
    {
        Tag::query()->create(['name' => 'Backend']);
        StepTemplate::query()->create(['name' => 'Release-Ablauf']);

        $this->assertContains(__('app.tags.title'), $this->groupsFor('backend'));
        $this->assertContains(__('app.templates.title'), $this->groupsFor('release-abl'));
    }

    public function test_releases_are_found_out_of_the_cache_without_touching_git(): void
    {
        $project = Project::query()->create(['name' => 'Testrepo', 'path' => sys_get_temp_dir()]);

        cache()->put('releases.'.auth()->id(), [
            $project->getKey() => [[
                'tag' => 'release-9.9.9',
                'at' => '2026-08-20T10:00:00+02:00',
                'subject' => 'Grosser Wurf',
            ]],
        ], 300);

        $this->assertContains(__('app.dev.releases'), $this->groupsFor('9.9.9'));
        $this->assertContains(__('app.dev.releases'), $this->groupsFor('grosser'));
    }

    public function test_pull_requests_are_found_out_of_the_review_cache(): void
    {
        cache()->put('reviews.'.auth()->id(), [
            'mine' => [[
                'title' => 'fix(zeit): Doppelbuchung',
                'number' => 42,
                'url' => 'https://github.test/pr/42',
                'repository' => 'acme/web',
                'draft' => false,
                'updated_at' => '2026-08-27T10:00:00+02:00',
                'created_at' => '2026-08-26T10:00:00+02:00',
            ]],
            'incoming' => [],
            'repositories' => [],
            'login' => 'ich',
            'error' => null,
            'fetched_at' => '2026-08-27T10:00:00+02:00',
        ], 600);

        $results = $this->get(route('search', ['q' => 'doppelbuchung']))->assertOk()->json('results');
        $pull = collect($results)->firstWhere('group', __('app.dev.my_pulls'));

        $this->assertNotNull($pull);
        $this->assertSame('https://github.test/pr/42', $pull['url']);
        $this->assertTrue($pull['external']);

        // the palette must never start a GitHub round trip
        Http::assertNothingSent();
    }

    public function test_the_four_original_sources_still_answer(): void
    {
        Todo::query()->create(['title' => 'Migration prüfen']);
        Snippet::query()->create(['title' => 'SSH', 'body' => 'ssh -A galawork']);
        DayNote::query()->create(['day' => '2026-08-27', 'body' => 'Deploy lief durch']);
        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-27 09:00:00',
            'ended_at' => '2026-08-27 17:00:00',
            'note' => 'Zeiterfassung gebaut',
        ]);

        $this->assertContains(__('app.nav.todos'), $this->groupsFor('migration'));
        $this->assertContains(__('app.dev.snippets'), $this->groupsFor('galawork'));
        $this->assertContains(__('app.notes.title'), $this->groupsFor('deploy'));
        $this->assertContains(__('app.nav.history'), $this->groupsFor('zeiterfassung'));
    }
}
