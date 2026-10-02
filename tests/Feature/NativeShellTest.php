<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The app window gets a page that arrives whole: no cross-document transition and no entrance
 * animation. The system WebKit rendered named transition elements blank and the new page with an
 * empty content column for a moment — a flash of bare canvas on every click.
 */
class NativeShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_app_window_opts_out_of_transitions_and_arrivals(): void
    {
        $this->login();

        $response = $this->withServerVariables(['HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh) AppleWebKit/605.1.15 TaktShell/1.0'])
            ->get(route('settings'))
            ->assertOk()
            ->assertSee('data-shell="native"', escape: false)
            ->assertSee('data-settled', escape: false)
            ->assertSee('@view-transition { navigation: none; }', escape: false);

        // after the stylesheet, so it is the rule that wins
        $html = $response->getContent();
        $this->assertGreaterThan(strpos($html, 'app.css') ?: strpos($html, 'build/assets'), strpos($html, 'navigation: none'));
    }

    public function test_a_browser_keeps_the_transitions(): void
    {
        $this->login();

        $this->withServerVariables(['HTTP_USER_AGENT' => 'Mozilla/5.0 Safari/605.1.15'])
            ->get(route('settings'))
            ->assertOk()
            ->assertDontSee('data-shell="native"', escape: false)
            ->assertDontSee('navigation: none', escape: false)
            ->assertDontSee('data-settled', escape: false);
    }
}
