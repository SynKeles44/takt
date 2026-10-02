<?php

use App\Http\Middleware\SetLocale;
use App\Support\DataDirectory;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

/*
 * A bundled Takt runs read-only from inside the app and writes next to the user's library
 * instead — see App\Support\DataDirectory. The project checkout, Docker and the tests never set
 * the variable and keep every path where it is. The framework's own caches move along: it writes
 * them under bootstrap/cache on first boot, and inside a signed bundle that write would fail.
 */
$data = DataDirectory::fromEnvironment();

if ($data !== null) {
    // before anything boots: the framework writes its package manifest during the first boot
    $data->prepare();

    $paths = ['DB_DATABASE' => $data->database().'/database.sqlite'];

    foreach (['APP_SERVICES_CACHE' => 'services', 'APP_PACKAGES_CACHE' => 'packages', 'APP_CONFIG_CACHE' => 'config', 'APP_ROUTES_CACHE' => 'routes-v7', 'APP_EVENTS_CACHE' => 'events'] as $variable => $file) {
        $paths[$variable] = $data->cache().'/'.$file.'.php';
    }

    // only the SQLite file moves, not database_path(): the migrations stay with the code
    foreach ($paths as $variable => $path) {
        putenv($variable.'='.$path);
        $_ENV[$variable] = $_SERVER[$variable] = $path;
    }
}

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

if ($data !== null) {
    $app->useEnvironmentPath($data->path);
    $app->useStoragePath($data->storage());
}

return $app;
