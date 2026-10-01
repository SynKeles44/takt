<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Forms that act without reloading the page.
 *
 * A form marked `data-live` posts in place and only the marked regions are replaced. The check
 * here is on the MARKUP rather than on behaviour, deliberately: the swap itself is JavaScript and
 * a PHP test cannot see it, but what a PHP test CAN see is a form that was never marked — which
 * is how all 36 of them ended up reloading the page in the first place.
 */
class LiveFormTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The forms that are supposed to navigate, and why.
     *
     * Each one leaves the app or is driven by its own JavaScript, so a swap would either be wrong
     * or would fight the module that owns it.
     */
    private const array NAVIGATES = [
        'auth/login.blade.php',             // leaves the app
        'auth/register.blade.php',          // leaves the app
        'commands.blade.php',               // the run-input form, owned by command-runner.js
        'components/choice-carousel.blade.php', // owned by the carousel module
        'components/app-layout.blade.php',  // logout
        'components/todo-row.blade.php',    // data-async, its own optimistic path
        'todos/show.blade.php',             // data-async, same
        'widgets/test-post.blade.php',      // a shortcut TO the testpost page
    ];

    public function test_every_form_either_acts_in_place_or_is_a_known_navigation(): void
    {
        $reloading = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());

            if (in_array($relative, self::NAVIGATES, true)) {
                continue;
            }

            $body = (string) $file->getContents();
            $forms = substr_count($body, '<form');
            $live = substr_count($body, 'data-live') + substr_count($body, 'data-async');

            if ($forms > $live) {
                $reloading[] = $relative.' ('.($forms - $live).')';
            }
        }

        $this->assertSame([], $reloading, 'these forms still reload the whole page');
    }

    /** The exemption list has to stay honest: an entry for a file with no form at all is rot. */
    public function test_every_exemption_names_a_file_that_has_a_form(): void
    {
        foreach (self::NAVIGATES as $relative) {
            $path = resource_path('views/'.$relative);

            $this->assertFileExists($path);
            $this->assertStringContainsString('<form', (string) file_get_contents($path), "{$relative} is exempted but has no form");
        }
    }
}
