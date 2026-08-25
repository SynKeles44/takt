<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Snippet;
use App\Models\User;
use App\Services\Commits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class DeveloperTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->login(['email' => 'dev@example.test']);
    }

    /** A throwaway repository with one commit, so the reader works on real git output. */
    private function repository(string $subject = 'Erste Änderung'): string
    {
        $path = sys_get_temp_dir().'/takt-repo-'.bin2hex(random_bytes(4));

        mkdir($path, 0o755, true);
        file_put_contents($path.'/README.md', "hello\n");

        foreach ([
            ['git', 'init', '-q', '-b', 'main'],
            ['git', 'config', 'user.email', 'dev@example.test'],
            ['git', 'config', 'user.name', 'Dev'],
            ['git', 'add', '-A'],
            ['git', 'commit', '-q', '-m', $subject],
        ] as $command) {
            Process::path($path)->run($command)->throw();
        }

        return $path;
    }

    public function test_todays_commits_are_listed_per_project(): void
    {
        $path = $this->repository('Zeiterfassung korrigiert');

        Project::query()->create(['name' => 'Testrepo', 'path' => $path]);

        $this->get(route('dev'))
            ->assertOk()
            ->assertSee('Testrepo')
            ->assertSee('Zeiterfassung korrigiert')
            ->assertSee('Ein Commit an diesem Tag');

        // yesterday has none
        $this->get(route('dev', ['tag' => Carbon::yesterday()->toDateString()]))
            ->assertOk()
            ->assertSee('Kein Commit an diesem Tag');

        exec('/bin/rm -rf '.escapeshellarg($path));
    }

    public function test_a_missing_folder_is_reported_instead_of_failing(): void
    {
        Project::query()->create(['name' => 'Weg', 'path' => '/tmp/gibt-es-nicht-'.bin2hex(random_bytes(3))]);

        $this->get(route('dev'))->assertOk()->assertSee('Ordner nicht gefunden');
    }

    public function test_a_folder_without_git_is_reported(): void
    {
        $path = sys_get_temp_dir().'/takt-plain-'.bin2hex(random_bytes(4));
        mkdir($path, 0o755, true);

        Project::query()->create(['name' => 'Ohne Git', 'path' => $path]);

        $this->get(route('dev'))->assertOk()->assertSee('Kein Git-Repository');

        rmdir($path);
    }

    public function test_commits_of_another_account_stay_out_of_the_list(): void
    {
        $other = User::factory()->create();
        $path = $this->repository('Fremder Commit');

        Project::query()->forceCreate(['user_id' => $other->id, 'name' => 'Fremd', 'path' => $path]);

        $this->get(route('dev'))->assertOk()->assertDontSee('Fremder Commit')->assertDontSee('Fremd');

        exec('/bin/rm -rf '.escapeshellarg($path));
    }

    public function test_a_project_is_registered_updated_and_removed(): void
    {
        $this->post(route('projects.store'), [
            'name' => 'Takt',
            'path' => '~/PhpstormProjects/Takt',
            'repository' => 'SynKeles44/takt',
            'start_command' => 'make start',
            'port' => 8000,
        ])->assertRedirect()->assertSessionHas('status');

        $project = Project::query()->firstOrFail();

        $this->assertSame('SynKeles44/takt', $project->slug());
        $this->assertSame(8000, $project->port);

        $this->put(route('projects.update', $project), [
            'name' => 'Takt',
            'path' => '~/PhpstormProjects/Takt',
            'repository' => 'https://github.com/SynKeles44/takt.git',
            'port' => 8001,
        ])->assertRedirect();

        $this->assertSame('SynKeles44/takt', $project->fresh()->slug(), 'a full URL is reduced to owner/repo');
        $this->assertSame(8001, $project->fresh()->port);

        $this->delete(route('projects.destroy', $project))->assertRedirect();
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_the_same_folder_is_not_registered_twice(): void
    {
        Project::query()->create(['name' => 'Eins', 'path' => '/tmp/gleich']);

        $this->post(route('projects.store'), ['name' => 'Zwei', 'path' => '/tmp/gleich'])
            ->assertSessionHasErrors('path');

        $this->assertDatabaseCount('projects', 1);
    }

    public function test_the_review_list_needs_a_token_first(): void
    {
        $this->get(route('dev'))->assertOk()->assertSee('GitHub-Token');

        $this->put(route('settings.developer'), ['github_token' => 'ghp_test'])->assertRedirect();
        $this->assertSame('ghp_test', $this->user->fresh()->github_token);

        // empty keeps it, "-" clears it
        $this->put(route('settings.developer'), ['github_token' => '']);
        $this->assertSame('ghp_test', $this->user->fresh()->github_token);

        $this->put(route('settings.developer'), ['github_token' => '-']);
        $this->assertNull($this->user->fresh()->github_token);
    }

    /** @param  array<int, array<string, mixed>>  $pulls */
    private function fakeGithub(array $pulls, int $status = 200): void
    {
        Http::fake([
            'api.github.com/user' => Http::response(['login' => 'ich']),
            'api.github.com/repos/*/pulls*' => Http::response($pulls, $status),
            'api.github.com/search/issues*' => Http::response(['items' => []]),
        ]);
    }

    private function project(string $repository = 'galabau-workgroup/galawork-web'): Project
    {
        return Project::query()->create([
            'name' => 'Galawork Web',
            'path' => base_path(),
            'repository' => $repository,
            'position' => 0,
        ]);
    }

    public function test_open_pull_requests_are_shown_in_both_directions(): void
    {
        $this->user->update(['github_token' => 'ghp_test']);
        $this->project();

        $this->fakeGithub([
            [
                'title' => 'Gerätezeiten zurücksetzen',
                'number' => 2456,
                'html_url' => 'https://github.com/galabau-workgroup/galawork-web/pull/2456',
                'draft' => false,
                'user' => ['login' => 'kollege'],
                'requested_reviewers' => [['login' => 'ich']],
                'updated_at' => now()->subDays(2)->toIso8601String(),
                'created_at' => now()->subDays(3)->toIso8601String(),
            ],
            [
                'title' => 'Eigener Entwurf',
                'number' => 12,
                'html_url' => 'https://github.com/galabau-workgroup/galawork-web/pull/12',
                'draft' => true,
                'user' => ['login' => 'ich'],
                'requested_reviewers' => [],
                'updated_at' => now()->toIso8601String(),
                'created_at' => now()->toIso8601String(),
            ],
        ]);

        // the sections are fetched after the page, because GitHub costs over a second
        $this->get(route('dev.reviews.sections'))
            ->assertOk()
            ->assertSee('Wartet auf mich')
            ->assertSee('Gerätezeiten zurücksetzen')
            ->assertSee('Eigener Entwurf')
            ->assertSee('Entwurf');

        // once they are cached the page itself carries them
        $this->get(route('dev'))
            ->assertOk()
            ->assertSee('Gerätezeiten zurücksetzen')
            ->assertDontSee(__('app.dev.reviews_loading'));
    }

    public function test_the_page_does_not_wait_for_github_when_nothing_is_cached(): void
    {
        $this->user->update(['github_token' => 'ghp_test']);

        $this->fakeGithub([]);

        $this->get(route('dev'))->assertOk()->assertSee(__('app.dev.reviews_loading'));

        Http::assertNothingSent();
    }

    public function test_my_pull_requests_are_grouped_per_project(): void
    {
        $this->user->update(['github_token' => 'ghp_test']);
        $this->project();

        Http::fake([
            'api.github.com/user' => Http::response(['login' => 'ich']),
            'api.github.com/repos/*/pulls*' => Http::response([[
                'title' => 'Im Projekt',
                'number' => 1,
                'html_url' => 'https://github.test/1',
                'draft' => false,
                'user' => ['login' => 'ich'],
                'requested_reviewers' => [],
                'updated_at' => now()->toIso8601String(),
                'created_at' => now()->toIso8601String(),
            ]]),
            // a pull request in a repository that is not a registered project
            'api.github.com/search/issues*' => Http::response(['items' => [[
                'title' => 'Woanders',
                'number' => 2,
                'html_url' => 'https://github.test/2',
                'repository_url' => 'https://api.github.com/repos/somebody/else',
                'draft' => false,
                'updated_at' => now()->toIso8601String(),
                'created_at' => now()->toIso8601String(),
            ]]]),
        ]);

        $this->get(route('dev.reviews.sections'))
            ->assertOk()
            ->assertSee(__('app.dev.my_pulls'))
            ->assertSeeInOrder(['Galawork Web', 'Im Projekt'])
            ->assertSeeInOrder([__('app.dev.other_repositories'), 'Woanders']);
    }

    public function test_a_repository_the_token_cannot_see_says_so(): void
    {
        $this->user->update(['github_token' => 'ghp_public_only']);
        $this->project();

        // a private repository answers 404 for a token without the repo scope — search would
        // just return nothing, which is why the repository endpoint is asked directly
        $this->fakeGithub(['message' => 'Not Found'], status: 404);

        $this->get(route('dev.reviews.sections'))
            ->assertOk()
            ->assertSee(__('app.dev.no_repo_access'))
            ->assertSee('galabau-workgroup/galawork-web');
    }

    public function test_paging_through_the_days_only_replaces_the_commits(): void
    {
        Project::query()->create(['name' => 'Takt', 'path' => base_path(), 'position' => 0]);

        $response = $this->get(route('dev'))->assertOk();

        // the two regions the day navigation swaps — the reviews are in neither of them
        $response->assertSee('data-region="dev-head"', escape: false);
        $response->assertSee('data-region="dev-commits"', escape: false);
        $response->assertSee('data-partial="dev-head dev-commits"', escape: false);

        $content = (string) $response->getContent();
        $reviews = strpos($content, 'data-reviews-slot');
        $commitsEnd = strpos($content, 'data-region="dev-commits"');

        $this->assertNotFalse($reviews);
        $this->assertGreaterThan($commitsEnd, $reviews);
    }

    public function test_the_commits_of_each_project_can_be_collapsed(): void
    {
        Project::query()->create(['name' => 'Takt', 'path' => base_path(), 'position' => 0]);

        $this->get(route('dev'))
            ->assertOk()
            ->assertSee('<details', escape: false)
            ->assertSee('data-remember="commits.', escape: false);
    }

    public function test_a_rejected_token_is_reported_plainly(): void
    {
        $this->user->update(['github_token' => 'ghp_broken']);

        Http::fake(['api.github.com/*' => Http::response(['message' => 'Bad credentials'], 401)]);

        $this->get(route('dev.reviews.sections'))->assertOk()->assertSee('Das GitHub-Token wird abgelehnt (401)');
    }

    public function test_snippets_are_stored_copied_and_counted(): void
    {
        $this->post(route('snippets.store'), [
            'title' => 'Testdatenbank zurücksetzen',
            'body' => 'php artisan migrate:fresh --seed',
            'label' => 'artisan',
        ])->assertRedirect();

        $snippet = Snippet::query()->firstOrFail();

        $this->get(route('snippets'))->assertOk()->assertSee('php artisan migrate:fresh --seed');
        $this->get(route('dev'))->assertOk()->assertSee('Testdatenbank zurücksetzen');

        $this->postJson(route('snippets.used', $snippet))->assertOk()->assertJson(['uses' => 1]);
        $this->assertSame(1, $snippet->fresh()->uses);

        $this->delete(route('snippets.destroy', $snippet))->assertRedirect();
        $this->assertDatabaseCount('snippets', 0);
    }

    public function test_the_palette_finds_snippets_and_offers_them_for_copying(): void
    {
        Snippet::query()->create(['title' => 'SSH zum Testserver', 'body' => 'ssh deploy@test.example']);

        $results = $this->getJson(route('search', ['q' => 'ssh']))->assertOk()->json('results');

        $this->assertSame('SSH zum Testserver', $results[0]['label']);
        $this->assertSame('ssh deploy@test.example', $results[0]['copy']);
        $this->assertStringContainsString('kopiert', $results[0]['ping']);
    }

    public function test_snippets_of_another_account_are_invisible(): void
    {
        $other = User::factory()->create();
        Snippet::query()->forceCreate(['user_id' => $other->id, 'title' => 'Fremd', 'body' => 'secret']);

        $this->get(route('snippets'))->assertOk()->assertDontSee('Fremd');
        $this->getJson(route('search', ['q' => 'secret']))->assertOk()->assertExactJson(['results' => []]);
    }

    public function test_the_day_is_validated(): void
    {
        $this->get(route('dev', ['tag' => 'gestern']))->assertSessionHasErrors('tag');
    }

    public function test_the_commits_service_reads_the_repository_email_too(): void
    {
        // the repository commits under an address that is not the account address
        $path = sys_get_temp_dir().'/takt-repo-'.bin2hex(random_bytes(4));
        mkdir($path, 0o755, true);
        file_put_contents($path.'/a.txt', 'x');

        foreach ([
            ['git', 'init', '-q', '-b', 'main'],
            ['git', 'config', 'user.email', 'work@example.test'],
            ['git', 'config', 'user.name', 'Work'],
            ['git', 'add', '-A'],
            ['git', 'commit', '-q', '-m', 'Arbeitsidentität'],
        ] as $command) {
            Process::path($path)->run($command)->throw();
        }

        $project = Project::query()->create(['name' => 'Arbeit', 'path' => $path]);

        $groups = app(Commits::class)->forDay(Carbon::today(), collect([$project]));

        $this->assertSame('Arbeitsidentität', $groups->first()['commits'][0]['subject']);

        exec('/bin/rm -rf '.escapeshellarg($path));
    }
}
