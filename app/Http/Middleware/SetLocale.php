<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Number;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    private const array SUPPORTED = ['de', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale;

        if (in_array($locale, self::SUPPORTED, true)) {
            App::setLocale($locale);
            Carbon::setLocale($locale);
            Date::setLocale($locale);
            Number::useLocale($locale);
        }

        return $next($request);
    }
}
