<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Theme;
use Tests\TestCase;

/**
 * The compiled stylesheet is the only place where a rule can silently disappear
 * during an edit — that is exactly how the popovers turned transparent once.
 */
class StylesheetTest extends TestCase
{
    private function stylesheet(): string
    {
        $files = glob(public_path('build/assets/app-*.css'));

        if ($files === false || $files === []) {
            $this->markTestSkipped('Run `npm run build` first.');
        }

        /*
         * The newest, not the first: the build hashes its filename and leaves the previous one
         * behind, so alphabetical order can hand back a stale stylesheet — a test that reads the
         * wrong file passes or fails for reasons that have nothing to do with the source.
         */
        usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return (string) file_get_contents($files[0]);
    }

    public function test_no_custom_property_refers_to_itself(): void
    {
        $css = $this->stylesheet();

        /*
         * `--ease: var(--ease)` is a cycle, which makes the property invalid at computed-value
         * time — and everything downstream of it falls back to its initial value. It shipped
         * once: a search-and-replace turning the literal easing curve into the token also
         * rewrote the token's own definition, and every animation using the shorthand
         * `animation: … var(--ease) …` silently stopped. Nothing rendered an error.
         */
        preg_match_all('/--([a-z0-9-]+)\s*:\s*var\(\s*--([a-z0-9-]+)\s*\)/i', $css, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $this->assertNotSame(
                mb_strtolower($match[1]),
                mb_strtolower($match[2]),
                sprintf('--%s is defined as itself, which makes it and everything using it invalid.', $match[1]),
            );
        }
    }

    /**
     * The soft page change rests on a few lines in the stylesheet, and all of them have to survive
     * the build: the opt-in, the one name that lets the content move on its own, and no name on
     * the sidebar — WebKit captures a named aside as a blank image, which made the sidebar
     * disappear for the length of every transition.
     */
    public function test_the_page_transition_survives_the_build(): void
    {
        $css = $this->stylesheet();

        $this->assertMatchesRegularExpression(
            '/@view-transition\s*\{[^}]*navigation\s*:\s*auto/i',
            $css,
            'Without the opt-in every navigation is a hard load.',
        );

        $flat = str_replace(' ', '', $css);

        $this->assertStringContainsString('view-transition-name:takt-page', $flat);
        $this->assertStringNotContainsString('view-transition-name:takt-nav', $flat, 'a named sidebar is a blank sidebar in WebKit');
        // both halves of the content fade on the same clock, so their opacities add up to one
        $this->assertMatchesRegularExpression('/::view-transition-old\(takt-page\)\{animation:takt-page-out \.32s var\(--ease\)/', $css);
        $this->assertMatchesRegularExpression('/::view-transition-new\(takt-page\)\{animation:takt-page-in \.32s var\(--ease\)/', $css);
    }

    public function test_the_motion_tokens_resolve_to_real_values(): void
    {
        $css = $this->stylesheet();

        // the tokens every animation in this file is written against
        $this->assertMatchesRegularExpression('/--ease:\s*cubic-bezier\(/', $css);
        $this->assertMatchesRegularExpression('/--spring:\s*cubic-bezier\(/', $css);
        $this->assertMatchesRegularExpression('/--dur:\s*[.0-9]+m?s/', $css);

        // and the two animations that carry the loading states
        $this->assertStringContainsString('@keyframes wb-shimmer', $css);
        $this->assertStringContainsString('@keyframes wb-spin', $css);
    }

    public function test_cards_and_slots_do_not_clip_what_reaches_past_their_edge(): void
    {
        $css = $this->stylesheet();

        /*
         * Paint containment on a card is a tempting performance knob and it broke two things
         * that deliberately live outside their box: the export menu on the evaluation page and
         * the widget remove button at -0.55rem. Both rendered as fragments, which reads as a
         * broken control rather than a clipped one.
         */
        foreach (['.surface', '.surface-plain', '.widget-slot'] as $selector) {
            $this->assertDoesNotMatchRegularExpression(
                '/'.preg_quote($selector, '/').'[^{}]*\{[^}]*contain:\s*(paint|strict|content)/',
                $css,
                $selector.' must not clip descendants that sit outside its box.',
            );
        }
    }

    public function test_neumorphism_lifts_its_surfaces_off_the_canvas(): void
    {
        $css = $this->stylesheet();

        // a surface that equals the canvas with soft shadows dissolves — it needs its own tone
        $this->assertStringNotContainsString(
            '[data-style=neumorphism]{--color-surface:var(--color-canvas)',
            $css,
        );

        // both directions of the extrusion, and the light scheme's own balance
        $this->assertMatchesRegularExpression('/\[data-style=neumorphism\][^{]*\{[^}]*--neu-dark:/', $css);
        $this->assertMatchesRegularExpression('/\[data-style=neumorphism\][^{]*\{[^}]*--neu-light:/', $css);
        $this->assertStringContainsString('[data-theme=daylight][data-style=neumorphism]', $css);
    }

    public function test_floating_surfaces_have_an_opaque_ground(): void
    {
        $css = $this->stylesheet();

        $this->assertMatchesRegularExpression('/\.nav-menu\{[^}]*background-color:var\(--color-popover\)/', $css);
        $this->assertMatchesRegularExpression('/\.dialog-panel\{[^}]*background-color:var\(--color-popover\)/', $css);

        // the root, every named theme, and any style that overrides it — all opaque
        preg_match_all('/--color-popover:\s*([^;}]+)/', $css, $matches);

        $named = count(array_filter(Theme::cases(), fn (Theme $theme): bool => ! $theme->isAutomatic()));

        $this->assertGreaterThanOrEqual($named + 1, count($matches[1]));

        foreach ($matches[1] as $value) {
            $value = trim($value);

            // a plain hex (the minifier shortens #ffffff to #fff), or a mix of opaque colours
            $opaque = preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value) === 1
                || (str_starts_with($value, 'color-mix(') && ! str_contains($value, 'transparent'));

            $this->assertTrue($opaque, 'Translucent popover ground: '.$value);
        }
    }

    public function test_the_component_classes_the_views_rely_on_are_compiled(): void
    {
        $css = $this->stylesheet();

        foreach (['.surface{', '.row{', '.btn{', '.check{', '.nav-item{', '.nav-menu{', '.toast{', '.pill{'] as $selector) {
            $this->assertStringContainsString($selector, $css, $selector.' is missing from the stylesheet');
        }
    }

    public function test_the_app_window_overrides_stay_unlayered(): void
    {
        $css = $this->stylesheet();
        $position = strpos($css, 'data-shell=native');

        $this->assertNotFalse($position);
        $this->assertGreaterThan(strlen($css) * 0.8, $position, 'the app-window overrides must stay at the end, outside every layer');
    }

    /**
     * The Apple style follows the Human Interface Guidelines on where glass may appear: the
     * functional layer only. So the content surfaces run without blur, the sidebar carries the
     * material, and both accessibility fallbacks the HIG names survive the build.
     */
    public function test_the_apple_style_keeps_glass_on_the_functional_layer_only(): void
    {
        $css = $this->stylesheet();

        $this->assertMatchesRegularExpression('/\[data-style=apple\]\{[^}]*--blur:0(px)?[;}]/', $css);
        $this->assertMatchesRegularExpression('/\[data-style=apple\] \.nav-aside\{[^}]*backdrop-filter:blur\(/', $css);
        $this->assertMatchesRegularExpression('/@media \(prefers-reduced-transparency:reduce\)\{\[data-style=apple\] \.nav-aside\{[^}]*backdrop-filter:none/', $css);
        $this->assertStringContainsString('@media (prefers-contrast:more){[data-style=apple]{', $css);

        // the macOS body size: a 15px root puts 0.875rem at 13px, and nothing drops below 10px
        $this->assertMatchesRegularExpression('/\[data-style=apple\]\{[^}]*--root-size:15px/', $css);
    }
}
