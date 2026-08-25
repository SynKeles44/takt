<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Reviews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The review cache is exercised against the store the app really uses. Carbon objects in
 * there came back as incomplete objects and took the development page down with them.
 */
class ReviewCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'database']);

        Http::fake([
            'api.github.com/user' => Http::response(['login' => 'ich']),
            'api.github.com/search/issues*' => Http::response(['items' => [[
                'title' => 'Fix the thing',
                'number' => 7,
                'html_url' => 'https://github.test/pr/7',
                'repository_url' => 'https://api.github.com/repos/example/takt',
                'draft' => false,
                'updated_at' => '2026-08-24T10:00:00Z',
                'created_at' => '2026-08-23T09:00:00Z',
            ]]]),
        ]);
    }

    public function test_a_cached_answer_comes_back_with_real_dates(): void
    {
        $user = $this->login(['github_token' => 'ghp_test']);
        $service = app(Reviews::class);

        $first = $service->forUser($user);
        $second = $service->forUser($user);

        // the second call is served from the store, which is where it used to break
        Http::assertSentCount(2);

        foreach ([$first, $second] as $result) {
            $this->assertInstanceOf(Carbon::class, $result['fetched_at']);
            $this->assertInstanceOf(Carbon::class, $result['mine'][0]['updated_at']);
            $this->assertInstanceOf(Carbon::class, $result['mine'][0]['created_at']);
            $this->assertSame('example/takt', $result['mine'][0]['repository']);
            $this->assertNull($result['error']);
        }
    }

    public function test_only_strings_and_numbers_are_stored(): void
    {
        $user = $this->login(['github_token' => 'ghp_test']);

        app(Reviews::class)->forUser($user);

        $stored = Cache::get('reviews.'.$user->getKey());

        $flat = function (array $value) use (&$flat): void {
            foreach ($value as $item) {
                if (is_array($item)) {
                    $flat($item);

                    continue;
                }

                $this->assertIsNotObject($item);
            }
        };

        $flat($stored);
    }

    public function test_a_broken_cache_entry_is_thrown_away_instead_of_rendered(): void
    {
        $user = $this->login(['github_token' => 'ghp_test']);

        Cache::put('reviews.'.$user->getKey(), 'nonsense', 120);

        $result = app(Reviews::class)->forUser($user);

        $this->assertInstanceOf(Carbon::class, $result['fetched_at']);
        $this->assertCount(1, $result['mine']);
    }

    public function test_the_development_page_renders_what_the_cache_holds(): void
    {
        $user = $this->login(['github_token' => 'ghp_test']);

        // the page never fetches itself; the sections endpoint fills the cache
        $this->get(route('dev.reviews.sections'))->assertOk()->assertSee('Fix the thing');

        // and this is the read-back that used to take the page down
        $this->get(route('dev'))->assertOk()->assertSee('Fix the thing');

        $this->assertNotNull(app(Reviews::class)->cached($user));
    }
}
