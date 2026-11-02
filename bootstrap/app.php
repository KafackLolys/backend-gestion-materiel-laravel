<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

if (PHP_OS_FAMILY === 'Windows') {
    foreach ([
        'APP_CONFIG_CACHE' => 'config.php',
        'APP_EVENTS_CACHE' => 'events.php',
        'APP_PACKAGES_CACHE' => 'packages.php',
        'APP_ROUTES_CACHE' => 'routes.php',
        'APP_SERVICES_CACHE' => 'services.php',
    ] as $key => $file) {
        $path = 'bootstrap'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.$file;

        putenv("{$key}={$path}");
        $_ENV[$key] = $path;
        $_SERVER[$key] = $path;
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        apiPrefix: env('VERCEL') ? '' : 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
