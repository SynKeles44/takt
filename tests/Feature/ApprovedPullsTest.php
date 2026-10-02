<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Widget;
use App\Services\Reviews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Pull requests of mine that have cleared review.
 *
 * Approved is GitHub's own verdict, asked for with `review:approved` — the review DECISION of the
 * pull request, so a later "changes requested" takes it back out on its own. The alternative,
 * reading `/pulls/{n}/reviews` per pull request, is one request each and still leaves this side
 * working out which verdict is current.
 */
class ApprovedPullsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Process::fake();
        Process::preventStrayProcesses();
        Http::preventStrayRequests();
    }

    public function test_the_approved_list_comes_from_its_own_search(): void
    {
        $this->fakeGithub();
        $this->login(['github_token' => 'ghp_test']);

        $result = app(Reviews::class)->forUser(auth()->user());

        $this->assertCount(1, $result['approved']);
        $this->assertSame('COR-1 durch das Review', $result['approved'][0]['title']);

        Http::assertSent(fn ($request): bool => str_contains(urldecode((string) $request->url()), 'review:approved'));
    }

    /**
     * The same pull request belongs in both lists. Removing it from the long one would hide it
     * from the place that groups it by project.
     */
    public function test_an_approved_pull_request_stays_in_my_own_list_too(): void
    {
        $this->fakeGithub();
        $this->login(['github_token' => 'ghp_test']);

        $result = app(Reviews::class)->forUser(auth()->user());

        $this->assertContains('COR-1 durch das Review', array_column($result['mine'], 'title'));
    }

    /** A store written before this list existed must not break the page that reads it. */
    public function test_a_cached_answer_without_the_key_still_hydrates(): void
    {
        $this->login(['github_token' => 'ghp_test']);

        Cache::put('reviews.'.auth()->id(), [
            'mine' => [], 'incoming' => [], 'repositories' => [],
            'login' => 'seymen', 'error' => null, 'fetched_at' => now()->toIso8601String(),
        ], 600);

        $this->assertSame([], app(Reviews::class)->forUser(auth()->user())['approved']);
        Http::assertNothingSent();
    }

    public function test_the_overview_shows_the_card_only_when_something_is_approved(): void
    {
        $this->fakeGithub();
        $this->login(['github_token' => 'ghp_test']);

        app(Reviews::class)->forUser(auth()->user());

        $this->get(route('dev.reviews.sections'))
            ->assertOk()
            ->assertSee(__('app.dev.approved'))
            ->assertSee('COR-1 durch das Review');
    }

    public function test_the_card_is_absent_when_nothing_is_approved(): void
    {
        $this->fakeGithub(approved: []);
        $this->login(['github_token' => 'ghp_test']);

        app(Reviews::class)->forUser(auth()->user());

        $this->get(route('dev.reviews.sections'))
            ->assertOk()
            ->assertDontSee(__('app.dev.approved'));
    }

    public function test_the_widget_renders_and_is_known_to_the_dashboard(): void
    {
        $this->fakeGithub();
        $this->login(['github_token' => 'ghp_test']);

        $this->assertSame('widgets.approved-pulls', Widget::ApprovedPulls->view());
        $this->assertTrue(Widget::ApprovedPulls->isRemote(), 'it reads GitHub, so the dashboard must only pay for it when shown');

        $this->get(route('dashboard.widget', Widget::ApprovedPulls->value))
            ->assertOk()
            ->assertSee('COR-1 durch das Review');
    }

    /** @param  ?list<array<string, mixed>>  $approved */
    private function fakeGithub(?array $approved = null): void
    {
        $pull = [
            'title' => 'COR-1 durch das Review',
            'number' => 42,
            'html_url' => 'https://github.com/example/takt/pull/42',
            'repository_url' => 'https://api.github.com/repos/example/takt',
            'draft' => false,
            'updated_at' => '2026-10-01T10:00:00Z',
            'created_at' => '2026-09-30T09:00:00Z',
        ];

        Http::fake([
            'api.github.com/user' => Http::response(['login' => 'seymen']),
            // the approved search is the one carrying the qualifier; the plain one is not
            'api.github.com/search/issues?*review%3Aapproved*' => Http::response([
                'items' => $approved ?? [$pull],
            ]),
            'api.github.com/search/issues*' => Http::response(['items' => [$pull]]),
            'api.github.com/repos/*' => Http::response([]),
        ]);
    }
}
