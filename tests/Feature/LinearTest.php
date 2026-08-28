<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Linear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LinearTest extends TestCase
{
    use RefreshDatabase;

    private function answer(array $nodes = []): array
    {
        return ['data' => ['issues' => ['nodes' => $nodes]]];
    }

    private function issue(string $identifier, string $state = 'In Progress', string $type = 'started'): array
    {
        return [
            'identifier' => $identifier,
            'title' => 'Buchung korrigieren',
            'url' => 'https://linear.app/acme/issue/'.$identifier,
            'state' => ['name' => $state, 'type' => $type],
            'assignee' => ['displayName' => 'Seymen'],
            'priorityLabel' => 'High',
        ];
    }

    public function test_without_a_key_nothing_is_asked_and_nothing_breaks(): void
    {
        Http::fake();
        $user = $this->login();

        $this->assertFalse(app(Linear::class)->configured($user));
        $this->assertSame(['issues' => [], 'error' => null], app(Linear::class)->forIds($user, ['COR-1']));

        Http::assertNothingSent();
    }

    public function test_ids_are_grouped_into_one_request_by_team_and_number(): void
    {
        Http::fake(['api.linear.app/graphql' => Http::response($this->answer([
            $this->issue('COR-6839'),
            $this->issue('DEV-12', 'Done', 'completed'),
        ]))]);

        $user = $this->login(['linear_token' => 'lin_api_test']);

        $result = app(Linear::class)->forIds($user, ['COR-6839', 'DEV-12']);

        $this->assertSame('Buchung korrigieren', $result['issues']['COR-6839']['title']);
        $this->assertSame('completed', $result['issues']['DEV-12']['state_type']);
        $this->assertSame('Seymen', $result['issues']['COR-6839']['assignee']);
        $this->assertNull($result['error']);

        Http::assertSentCount(1);

        Http::assertSent(function ($request): bool {
            // Linear takes the key raw, and both filters travel as variables
            return $request->header('Authorization')[0] === 'lin_api_test'
                && $request['variables']['teams'] === ['COR', 'DEV']
                && $request['variables']['numbers'] === [6839.0, 12.0];
        });
    }

    public function test_a_second_call_is_served_from_the_cache(): void
    {
        Http::fake(['api.linear.app/graphql' => Http::response($this->answer([$this->issue('COR-1')]))]);
        $user = $this->login(['linear_token' => 'lin_api_test']);

        app(Linear::class)->forIds($user, ['COR-1']);
        app(Linear::class)->forIds($user, ['COR-1']);

        Http::assertSentCount(1);
    }

    public function test_an_id_linear_does_not_know_is_remembered_as_unknown(): void
    {
        Http::fake(['api.linear.app/graphql' => Http::response($this->answer())]);
        $user = $this->login(['linear_token' => 'lin_api_test']);

        $first = app(Linear::class)->forIds($user, ['COR-9999']);
        app(Linear::class)->forIds($user, ['COR-9999']);

        $this->assertNull($first['issues']['COR-9999']);
        Http::assertSentCount(1);
    }

    public function test_a_rejected_key_is_reported_instead_of_thrown(): void
    {
        Http::fake(['api.linear.app/graphql' => Http::response(['errors' => []], 401)]);
        $user = $this->login(['linear_token' => 'wrong']);

        $this->assertSame(__('app.linear.unauthorized'), app(Linear::class)->forIds($user, ['COR-1'])['error']);
    }

    public function test_a_graphql_error_carries_its_message(): void
    {
        Http::fake(['api.linear.app/graphql' => Http::response([
            'errors' => [['message' => 'Unknown argument "number"']],
        ])]);

        $user = $this->login(['linear_token' => 'lin_api_test']);
        $result = app(Linear::class)->forIds($user, ['COR-1']);

        $this->assertStringContainsString('Unknown argument', (string) $result['error']);
        $this->assertSame([], $result['issues']);
    }

    public function test_the_ticket_page_lists_what_linear_assigns_to_me(): void
    {
        Http::fake(['api.linear.app/graphql' => Http::response(['data' => ['viewer' => ['assignedIssues' => ['nodes' => [
            $this->issue('COR-6839'),
        ]]]]])]);

        $this->login(['linear_token' => 'lin_api_test']);

        $this->get(route('tickets'))
            ->assertOk()
            ->assertSee('COR-6839')
            ->assertSee('Buchung korrigieren')
            ->assertSee('In Progress');
    }

    public function test_a_failing_linear_is_reported_on_the_page(): void
    {
        Http::fake(['api.linear.app/graphql' => Http::response(['errors' => [['message' => 'kaputt']]])]);
        $this->login(['linear_token' => 'lin_api_test']);

        $this->get(route('tickets'))->assertOk()->assertSee('kaputt');
    }

    public function test_without_a_key_the_page_says_where_the_list_comes_from(): void
    {
        Http::fake();
        $this->login();

        $this->get(route('tickets'))->assertOk()->assertSee(__('app.tickets.no_token'));

        Http::assertNothingSent();
    }
}
