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

        return (string) file_get_contents($files[0]);
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
}
