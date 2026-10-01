<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use App\Support\Deferred;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * A page WITH its deferred content already in it.
     *
     * Slow pages render skeletons first and fetch their content on a second request carrying
     * `X-Defer` — see App\Support\Deferred. A test about what a page shows wants that second
     * request; a test about the skeletons uses a plain get() and asserts they are there.
     */
    protected function page(string $url): TestResponse
    {
        /*
         * `call` and not `withHeader`: withHeader sets a DEFAULT header, so it would stay on for
         * every later request of the same test — a test that read a page and then asserted the
         * skeletons of another would silently get the filled page instead. This header belongs to
         * this one request.
         */
        return $this->call('GET', $url, [], [], [], ['HTTP_'.str_replace('-', '_', mb_strtoupper(Deferred::HEADER)) => '1']);
    }

    protected function login(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);

        $this->actingAs($user);

        return $user;
    }
}
