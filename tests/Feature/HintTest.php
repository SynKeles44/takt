<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HintTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_settings_explain_how_to_set_slack_and_github_up(): void
    {
        $this->login();

        $response = $this->get(route('settings'))->assertOk();

        $response->assertSee(__('app.slack.guide_title'));
        $response->assertSee('User Token Scopes', escape: false);
        $response->assertSee('chat:write', escape: false);
        $response->assertSee('xoxp-', escape: false);

        $response->assertSee(__('app.dev.github_guide_title'));
        $response->assertSee('public_repo', escape: false);

        // the help sits behind an (i) instead of taking up the card
        $response->assertSee('class="hint"', escape: false);
        $response->assertSee('role="tooltip"', escape: false);

        // the links in it are real links, opened safely
        $response->assertSee('href="https://api.slack.com/apps" target="_blank" rel="noreferrer noopener"', escape: false);
        $response->assertSee('href="https://github.com/settings/tokens/new" target="_blank" rel="noreferrer noopener"', escape: false);
    }

    public function test_the_slack_guide_matches_what_slack_calls_it_today(): void
    {
        $this->login();

        // Slack renamed "From scratch" to "Blank app"
        $this->get(route('settings'))
            ->assertOk()
            ->assertSee('Blank app', escape: false)
            ->assertDontSee('From scratch', escape: false);
    }

    public function test_the_guides_are_translated(): void
    {
        $this->login(['locale' => 'en']);

        $this->get(route('settings'))
            ->assertOk()
            ->assertSee('Setting Slack up')
            ->assertSee('Creating a GitHub token');
    }
}
