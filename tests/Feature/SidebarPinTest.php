<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Sidebar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Sub-areas pinned into the sidebar.
 *
 * Two things can go wrong and only one of them is visible: a pin that does not appear, and a pin
 * that appears lit at the same time as its parent. The second is the one that matters — the
 * highlight travels between rows on the assumption that exactly one of them is current, so two
 * lit rows are two markers and the travel has nowhere to go.
 */
class SidebarPinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Process::fake();
        Process::preventStrayProcesses();

        $this->login();
    }

    public function test_a_pinned_sub_area_gets_a_row_of_its_own(): void
    {
        $this->assertStringNotContainsString(route('docker'), $this->navigation());

        auth()->user()->update(['sidebar_extras' => ['docker', 'releases']]);

        $navigation = $this->navigation();

        $this->assertStringContainsString(route('docker'), $navigation);
        $this->assertStringContainsString(route('releases'), $navigation);
    }

    /**
     * The sidebar's own list, not the page.
     *
     * Every route in the app appears in the command palette, which is on every page too — so a
     * whole-page assertion passes before anything has been pinned and proves nothing.
     */
    private function navigation(?string $url = null): string
    {
        $html = (string) $this->get($url ?? route('dashboard'))->assertOk()->getContent();

        $from = mb_strpos($html, '<nav class="nav-list');

        return $from === false ? '' : mb_substr($html, $from, mb_strpos($html, '</nav>', $from) - $from);
    }

    public function test_the_pin_is_lit_instead_of_its_section_not_alongside_it(): void
    {
        auth()->user()->update(['sidebar_extras' => ['docker']]);

        $html = (string) $this->get(route('docker'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'nav-item-active'));
        // and it is the pin, which the sub class is the only way to tell apart
        $this->assertStringContainsString('nav-item-active nav-item-sub', $html);
    }

    /**
     * Three ticket views share one route, so without reading the query a pinned board would light
     * up while the sprints are on screen — and both rows would carry a marker.
     */
    public function test_a_pinned_ticket_view_reads_the_query_not_only_the_route(): void
    {
        auth()->user()->update(['sidebar_extras' => ['tickets-board', 'tickets-sprints']]);

        foreach (['board', 'liste', 'sprints'] as $view) {
            $html = (string) $this->get(route('tickets', ['ansicht' => $view]))->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, 'nav-item-active'), "the {$view} view lights the wrong number of rows");
        }
    }

    public function test_the_fixed_sections_cannot_be_pinned_away(): void
    {
        auth()->user()->update(['sidebar_extras' => []]);

        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        foreach (Sidebar::sections() as $section) {
            $this->assertStringContainsString($section['label'], $html);
        }
    }

    /** The shipped state: the seven, in their order, and nothing underneath. */
    public function test_a_fresh_user_carries_the_sections_and_no_pins(): void
    {
        $user = auth()->user();

        $this->assertNull($user->sidebar_extras);
        $this->assertNull($user->sidebar_order);

        $navigation = $this->navigation();

        $this->assertSame(count(Sidebar::sections()), substr_count($navigation, 'class="nav-item'));
        $this->assertStringNotContainsString('nav-item-sub', $navigation);
    }

    public function test_the_sections_follow_a_stored_order(): void
    {
        auth()->user()->update(['sidebar_order' => ['tickets', 'dev', 'dashboard']]);

        $labels = [];
        preg_match_all('/nav-label relative hidden sm:inline">([^<]+)</', $this->navigation(), $labels);

        // the three that were named, in that order, and the rest after them in their shipped order
        $this->assertSame(
            [__('app.nav.tickets'), __('app.nav.dev'), __('app.nav.dashboard')],
            array_slice($labels[1], 0, 3),
        );
        $this->assertCount(count(Sidebar::sections()), $labels[1]);
    }

    /**
     * A section added in a later version is in nobody's stored order, and must not therefore
     * vanish from the sidebar of everyone who ever sorted it.
     */
    public function test_a_section_the_stored_order_does_not_name_still_appears(): void
    {
        auth()->user()->update(['sidebar_order' => ['dev']]);

        $navigation = $this->navigation();

        foreach (Sidebar::sections() as $section) {
            $this->assertStringContainsString($section['label'], $navigation);
        }
    }

    /** Storing the shipped order as a custom one would freeze today's sections into the account. */
    public function test_the_shipped_order_is_stored_as_no_order_at_all(): void
    {
        $this->put(route('settings.sidebar'), ['sidebar_order' => array_column(Sidebar::sections(), 'route')])
            ->assertSessionHasNoErrors();

        $this->assertNull(auth()->user()->fresh()->sidebar_order);
    }

    public function test_a_route_outside_the_seven_cannot_be_ordered_in(): void
    {
        $this->put(route('settings.sidebar'), ['sidebar_order' => ['dashboard', 'settings']])
            ->assertSessionHasErrors('sidebar_order.1');
    }

    public function test_a_key_nobody_offers_is_refused(): void
    {
        $this->put(route('settings.sidebar'), ['sidebar_extras' => ['docker', 'erfunden']])
            ->assertSessionHasErrors('sidebar_extras.1');

        $this->assertNull(auth()->user()->fresh()->sidebar_extras);
    }

    public function test_every_offered_key_builds_a_url_and_has_an_icon(): void
    {
        $icons = (string) file_get_contents(resource_path('views/components/icon.blade.php'));

        foreach (Sidebar::extras() as $extra) {
            $this->assertNotSame('', route($extra['route'], $extra['query'] ?? []));
            $this->assertStringContainsString("@case('{$extra['icon']}')", $icons, "{$extra['key']} points at an icon that does not exist");
        }
    }
}
