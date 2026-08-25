<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SlackPostTest extends TestCase
{
    use RefreshDatabase;

    private array $input = [
        'ticket' => 'COR-6944',
        'pr' => '2456',
        'instance' => 'b63d4865/mod/zeiterfassung',
    ];

    private function ready(): User
    {
        return $this->login(['slack_token' => 'xoxp-test-token', 'slack_channel' => '#testing']);
    }

    public function test_the_post_is_sent_with_the_users_own_token(): void
    {
        $this->ready();

        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'channel' => 'C1', 'ts' => '170.1']),
            'slack.com/api/chat.getPermalink*' => Http::response(['ok' => true, 'permalink' => 'https://slack.test/p1']),
        ]);

        $this->from(route('dev.testpost'))
            ->post(route('dev.testpost.send'), $this->input)
            ->assertRedirect(route('dev.testpost'))
            ->assertSessionHas('status', __('app.slack.sent'))
            ->assertSessionHas('slack_permalink', 'https://slack.test/p1');

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'chat.postMessage')) {
                return true;
            }

            return $request->hasHeader('Authorization', 'Bearer xoxp-test-token')
                && $request['channel'] === '#testing'
                && str_contains($request['text'], 'Ticket: https://linear.app/galawork/issue/COR-6944')
                && str_contains($request['text'], 'PR: https://github.com/galabau-workgroup/galawork-web/pull/2456')
                && str_contains($request['text'], 'Test-Instanz: https://b63d4865-web.galawork.dev/mod/zeiterfassung')
                && $request['unfurl_links'] === false;
        });
    }

    public function test_a_rejected_token_is_reported_in_plain_words(): void
    {
        $this->ready();

        Http::fake(['slack.com/api/*' => Http::response(['ok' => false, 'error' => 'invalid_auth'])]);

        $this->from(route('dev.testpost'))
            ->post(route('dev.testpost.send'), $this->input)
            ->assertRedirect(route('dev.testpost'))
            ->assertSessionHasErrors(['slack' => __('app.slack.invalid_token')]);
    }

    public function test_a_channel_the_user_is_not_in_is_reported(): void
    {
        $this->ready();

        Http::fake(['slack.com/api/*' => Http::response(['ok' => false, 'error' => 'not_in_channel'])]);

        $this->from(route('dev.testpost'))
            ->post(route('dev.testpost.send'), $this->input)
            ->assertSessionHasErrors(['slack' => __('app.slack.not_in_channel')]);
    }

    public function test_an_unknown_error_code_still_reaches_the_user(): void
    {
        $this->ready();

        Http::fake(['slack.com/api/*' => Http::response(['ok' => false, 'error' => 'something_else'])]);

        $this->from(route('dev.testpost'))
            ->post(route('dev.testpost.send'), $this->input)
            ->assertSessionHasErrors(['slack' => __('app.slack.failed', ['error' => 'something_else'])]);
    }

    public function test_a_post_without_a_token_is_refused_before_any_request(): void
    {
        $this->login();

        Http::fake();

        $this->from(route('dev.testpost'))
            ->post(route('dev.testpost.send'), $this->input)
            ->assertSessionHasErrors(['slack' => __('app.slack.not_configured')]);

        Http::assertNothingSent();
    }

    public function test_an_incomplete_block_is_never_posted(): void
    {
        $this->ready();

        Http::fake();

        $this->from(route('dev.testpost'))
            ->post(route('dev.testpost.send'), ['ticket' => 'COR-1'])
            ->assertSessionHasErrors('slack');

        Http::assertNothingSent();
    }

    public function test_the_button_only_shows_up_once_slack_is_set_up(): void
    {
        $this->login();

        $this->get(route('dev.testpost', $this->input))
            ->assertOk()
            ->assertDontSee(__('app.slack.send'))
            ->assertSee(__('app.slack.setup_note'));

        $this->ready();

        $this->get(route('dev.testpost', $this->input))
            ->assertOk()
            ->assertSee(__('app.slack.send'))
            ->assertSee(route('dev.testpost.send'), escape: false);
    }

    public function test_token_and_channel_are_stored_and_the_token_never_leaves_in_clear_text(): void
    {
        $user = $this->login();

        $this->put(route('settings.developer'), [
            'slack_token' => 'xoxp-secret',
            'slack_channel' => '#testing',
        ])->assertRedirect()->assertSessionHas('status');

        $user->refresh();

        $this->assertSame('xoxp-secret', $user->slack_token);
        $this->assertSame('#testing', $user->slack_channel);

        // stored encrypted, and never rendered back into the page
        $this->assertNotSame('xoxp-secret', DB::table('users')->where('id', $user->id)->value('slack_token'));
        $this->get(route('settings'))->assertOk()->assertDontSee('xoxp-secret');
    }

    public function test_an_empty_field_keeps_the_token_and_a_dash_clears_it(): void
    {
        $user = $this->ready();

        $this->put(route('settings.developer'), ['slack_channel' => '#testing']);
        $this->assertSame('xoxp-test-token', $user->fresh()->slack_token);

        $this->put(route('settings.developer'), ['slack_token' => '-', 'slack_channel' => '#testing']);
        $this->assertNull($user->fresh()->slack_token);
    }
}
