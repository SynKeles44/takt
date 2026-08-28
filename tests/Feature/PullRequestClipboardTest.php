<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Services\Reviews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PullRequestClipboardTest extends TestCase
{
    use RefreshDatabase;

    private function pull(string $url, string $repository = 'acme/web'): array
    {
        return [
            'title' => 'Etwas',
            'number' => 1,
            'url' => $url,
            'repository' => $repository,
            'draft' => false,
            'updated_at' => Carbon::parse('2026-08-27 10:00:00'),
            'created_at' => Carbon::parse('2026-08-26 10:00:00'),
        ];
    }

    public function test_a_group_becomes_its_heading_and_the_urls_below_it(): void
    {
        $text = app(Reviews::class)->clipboardText([
            'Webshop' => [$this->pull('https://github.test/pr/1'), $this->pull('https://github.test/pr/2')],
        ]);

        $this->assertSame("Webshop:\nhttps://github.test/pr/1\nhttps://github.test/pr/2", $text);
    }

    public function test_with_titles_a_pull_request_takes_two_lines(): void
    {
        $first = $this->pull('https://github.test/pr/1');
        $first['title'] = 'fix(zeit): Buchung und Abwesenheit am selben Tag';

        $second = $this->pull('https://github.test/pr/2');
        $second['title'] = 'feat(import): Positionen aus Dynamics';

        $text = app(Reviews::class)->clipboardText(['Webshop' => [$first, $second]], withTitles: true);

        $this->assertSame(
            "Webshop:\n"
            ."fix(zeit): Buchung und Abwesenheit am selben Tag\n"
            ."https://github.test/pr/1\n"
            ."\n"
            ."feat(import): Positionen aus Dynamics\n"
            .'https://github.test/pr/2',
            $text,
        );
    }

    public function test_with_titles_projects_stay_separated_by_a_blank_line(): void
    {
        $web = $this->pull('https://github.test/pr/1');
        $web['title'] = 'Erstes';

        $api = $this->pull('https://github.test/pr/9');
        $api['title'] = 'Zweites';

        $text = app(Reviews::class)->clipboardText(['Webshop' => [$web], 'API' => [$api]], withTitles: true);

        $this->assertSame(
            "Webshop:\nErstes\nhttps://github.test/pr/1\n\nAPI:\nZweites\nhttps://github.test/pr/9",
            $text,
        );
    }

    public function test_groups_are_separated_by_a_blank_line(): void
    {
        $text = app(Reviews::class)->clipboardText([
            'Webshop' => [$this->pull('https://github.test/pr/1')],
            'API' => [$this->pull('https://github.test/pr/9')],
        ]);

        $this->assertSame("Webshop:\nhttps://github.test/pr/1\n\nAPI:\nhttps://github.test/pr/9", $text);
    }

    public function test_a_group_without_pull_requests_is_left_out(): void
    {
        $text = app(Reviews::class)->clipboardText([
            'Leer' => [],
            'Webshop' => [$this->pull('https://github.test/pr/1')],
            'Auch leer' => [],
        ]);

        $this->assertSame("Webshop:\nhttps://github.test/pr/1", $text);
        $this->assertSame('', app(Reviews::class)->clipboardText(['Leer' => []]));
    }

    public function test_unregistered_repositories_are_grouped_under_their_own_name(): void
    {
        $groups = app(Reviews::class)->byRepository([
            $this->pull('https://github.test/pr/5', 'zeta/one'),
            $this->pull('https://github.test/pr/6', 'alpha/two'),
            $this->pull('https://github.test/pr/7', 'zeta/one'),
        ]);

        $this->assertSame(['alpha/two', 'zeta/one'], array_keys($groups));
        $this->assertCount(2, $groups['zeta/one']);
    }

    public function test_the_page_offers_a_button_per_pull_request_per_project_and_for_everything(): void
    {
        Http::fake([
            'api.github.com/user' => Http::response(['login' => 'ich']),
            'api.github.com/repos/acme/web/pulls*' => Http::response([[
                'title' => 'Zeiterfassung',
                'number' => 12,
                'html_url' => 'https://github.test/acme/web/pull/12',
                'user' => ['login' => 'ich'],
                'draft' => false,
                'updated_at' => '2026-08-27T10:00:00Z',
                'created_at' => '2026-08-26T10:00:00Z',
            ]]),
            'api.github.com/search/issues*' => Http::response(['items' => []]),
        ]);

        $this->login(['github_token' => 'ghp_test']);
        $project = Project::query()->create(['name' => 'Webshop', 'path' => sys_get_temp_dir(), 'repository' => 'acme/web']);

        $response = $this->get(route('dev.reviews.sections'))->assertOk();

        $response->assertSee('data-copy="https://github.test/acme/web/pull/12"', escape: false);
        $response->assertSee(__('app.dev.copy_all'));
        // Blade writes a real newline into the attribute; the browser keeps it in dataset.copy
        $response->assertSee("data-copy=\"Webshop:\nhttps://github.test/acme/web/pull/12\"", escape: false);
        $response->assertSee(__('app.dev.copy_project'));

        $this->assertNotNull($project->slug());
    }

    public function test_every_pull_request_carries_a_checkbox_and_the_group_its_heading(): void
    {
        Http::fake([
            'api.github.com/user' => Http::response(['login' => 'ich']),
            'api.github.com/repos/acme/web/pulls*' => Http::response([[
                'title' => 'Zeiterfassung',
                'number' => 12,
                'html_url' => 'https://github.test/acme/web/pull/12',
                'user' => ['login' => 'ich'],
                'draft' => false,
                'updated_at' => '2026-08-27T10:00:00Z',
                'created_at' => '2026-08-26T10:00:00Z',
            ]]),
            'api.github.com/search/issues*' => Http::response(['items' => []]),
        ]);

        $this->login(['github_token' => 'ghp_test']);
        Project::query()->create(['name' => 'Webshop', 'path' => sys_get_temp_dir(), 'repository' => 'acme/web']);

        $response = $this->get(route('dev.reviews.sections'))->assertOk();

        // the selection is applied in the browser, so the markup has to carry it
        $response->assertSee('data-pull-pick value="https://github.test/acme/web/pull/12"', escape: false);
        $response->assertSee('data-pull-group data-copy-heading="Webshop"', escape: false);
        $response->assertSee('data-copy-scope="group"', escape: false);
        $response->assertSee('data-copy-scope="all"', escape: false);
        $response->assertSee(__('app.dev.nothing_picked'));

        // the switch is off by default, and every box carries the title it would add
        $response->assertSee('data-copy-titles>', escape: false);
        $response->assertDontSee('data-copy-titles checked', escape: false);
        $response->assertSee('data-title="Zeiterfassung"', escape: false);
        $response->assertSee('data-copy-scope="pull"', escape: false);
    }
}
