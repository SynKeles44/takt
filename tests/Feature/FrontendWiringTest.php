<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Live forms replace whole regions of the page in place. A module that holds on to a node from
 * the first paint, or binds a listener straight onto one, is wired to an element that will be
 * thrown away by the next swap — the sidebar's account menu went dead exactly that way. So the
 * shape every module has to keep is: listen on `document` or `window`, look the element up when
 * the event arrives.
 */
class FrontendWiringTest extends TestCase
{
    public function test_no_module_caches_a_node_at_boot(): void
    {
        $offenders = [];

        foreach ($this->modules() as $name => $body) {
            // a module-level `const x = document.querySelector(…)` is the cache; inside a function it is a lookup
            preg_match_all('/^(?:const|let|var)\s+\w+\s*=\s*document\.querySelector\(/m', $body, $matches);

            foreach ($matches[0] as $match) {
                $offenders[] = $name.': '.trim($match);
            }
        }

        $this->assertSame([], $offenders, 'these modules keep a node from the first paint');
    }

    public function test_no_module_binds_a_listener_straight_onto_a_page_node(): void
    {
        $offenders = [];

        foreach ($this->modules() as $name => $body) {
            // `document.querySelectorAll(…).forEach(… addEventListener` — bound once, to nodes that will be swapped out
            preg_match_all('/document\.querySelectorAll\([^)]*\)\.forEach\(\([^)]*\)\s*=>\s*\{?\s*\n?\s*\w+\.addEventListener/m', $body, $matches);

            foreach ($matches[0] as $match) {
                $offenders[] = $name.': '.preg_replace('/\s+/', ' ', $match);
            }
        }

        $this->assertSame([], $offenders, 'these modules bind listeners onto nodes a swap replaces');
    }

    /** The boot file only calls modules; the behaviour lives in files that can be read one at a time. */
    public function test_the_boot_file_holds_no_listeners_of_its_own(): void
    {
        $boot = (string) file_get_contents(resource_path('js/app.js'));

        $this->assertStringNotContainsString('addEventListener', $boot);
        $this->assertStringNotContainsString('querySelector', $boot);
    }

    /** Every module that reacts to a swap listens for the event the swap fires — nothing polls. */
    public function test_the_swap_announces_itself_once(): void
    {
        $swap = (string) file_get_contents(resource_path('js/swap.js'));

        $this->assertSame(1, substr_count($swap, "new CustomEvent('takt:swapped'"));
    }

    /** @return array<string, string> */
    private function modules(): array
    {
        $modules = [];

        foreach (File::files(resource_path('js')) as $file) {
            $modules[$file->getFilename()] = (string) $file->getContents();
        }

        return $modules;
    }
}
