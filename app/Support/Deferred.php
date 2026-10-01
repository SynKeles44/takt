<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Pages that read something slow arrive before they have it.
 *
 * Linear, GitHub, `docker ps`, `composer outdated`, git across every registered project — all of
 * them are read while the HTTP response is being built, so a click used to buy a blank window for
 * as long as the slowest of them took. Nothing in the page needs that: the layout is known, the
 * shape of the content is known, and only the numbers are not.
 *
 * So the first request renders the page WITHOUT the slow read and with skeletons where the
 * content goes. The browser has a page immediately, and asks again — with `X-Defer` — for the
 * same URL. That second request does the slow work and its `[data-region]` content is swapped in.
 *
 * Deliberately one flag and not a framework: the controller asks `Deferred::wanted()` once,
 * skips the expensive call, and the view branches on it. Nothing is duplicated, no page needs a
 * second route, and a page that does not opt in behaves exactly as before.
 */
final class Deferred
{
    public const string HEADER = 'X-Defer';

    /**
     * Whether THIS request should skip the slow read and render skeletons instead.
     *
     * False for the follow-up request, and false for anything that is not a plain page view —
     * a partial swap, a form post, or a client that cannot ask again gets the real thing, because
     * a skeleton nobody will replace is worse than a wait.
     */
    public static function wanted(Request $request): bool
    {
        return ! $request->hasHeader(self::HEADER)
            && $request->isMethod('GET')
            && ! $request->ajax()
            && ! $request->wantsJson();
    }
}
