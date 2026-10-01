<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Deferred;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * The page arrives before its slow content does.
 *
 * The property that matters is not "there are skeletons" — it is that the FIRST request does not
 * touch the slow service at all. A page that renders skeletons and still waits for Linear has the
 * cost and none of the benefit, and no screenshot would tell the two apart.
 */
class DeferredPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Process::fake();
        Process::preventStrayProcesses();
        Http::preventStrayRequests();
        Http::fake(['api.linear.app/graphql' => Http::response(['data' => ['viewer' => ['assignedIssues' => ['nodes' => [[
            'identifier' => 'COR-1', 'title' => 'Ein Ticket', 'url' => '', 'updatedAt' => '2026-09-30T08:00:00.000Z',
            'state' => ['name' => 'Todo', 'type' => 'unstarted'], 'team' => ['key' => 'COR', 'name' => 'Core'],
        ]]]]]])]);

        $this->login(['linear_token' => 'lin_api_test']);
    }

    public function test_the_first_request_does_not_ask_linear_at_all(): void
    {
        $this->get(route('tickets'))->assertOk();

        Http::assertNothingSent();
    }

    public function test_the_first_request_renders_the_shape(): void
    {
        $html = (string) $this->get(route('tickets'))->assertOk()->getContent();

        $this->assertStringContainsString('data-defer=', $html);
        $this->assertStringContainsString('skeleton', $html);
        $this->assertStringNotContainsString('Ein Ticket', $html);
    }

    public function test_the_second_request_brings_the_content_and_drops_the_marker(): void
    {
        $html = (string) $this->page(route('tickets'))->assertOk()->getContent();

        $this->assertStringContainsString('Ein Ticket', $html);
        $this->assertStringNotContainsString('data-defer=', $html);

        // the board asks Linear for the issues and for each team's workflow — the point is that
        // the first request asked for neither
        Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), 'linear.app'));
    }

    /**
     * A client that cannot ask again must never be handed skeletons: a partial swap and a JSON
     * reader both get the real thing, because a skeleton nobody replaces is worse than a wait.
     */
    public function test_a_request_that_cannot_ask_again_is_not_deferred(): void
    {
        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('tickets'))
            ->assertOk()
            ->assertSee('Ein Ticket');
    }

    /**
     * Every page that defers says so, and says it in the way the browser looks for.
     *
     * A page that renders skeletons but forgets the marker is the worst of the three states: the
     * reader gets placeholders that are never replaced. One assertion over the whole set is what
     * stops the next deferred page from shipping that way.
     */
    public function test_every_deferred_page_marks_itself_and_fills_itself(): void
    {
        $pages = [
            route('tickets'),
            route('tickets.show', 'COR-1'),
            route('docker'),
            route('packages'),
            route('releases'),
        ];

        foreach ($pages as $url) {
            $first = (string) $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('data-defer="', $first, "{$url} renders without the marker");
            $this->assertStringContainsString('skeleton', $first, "{$url} defers without skeletons");

            $this->assertStringNotContainsString(
                'data-defer="',
                (string) $this->page($url)->assertOk()->getContent(),
                "{$url} still asks to be filled after it was",
            );
        }
    }

    public function test_the_header_name_is_the_one_the_browser_sends(): void
    {
        $this->assertSame('X-Defer', Deferred::HEADER);
    }
}
