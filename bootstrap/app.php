<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {

            Route::middleware('api')
                ->prefix('api/admin')
                ->group(base_path('routes/api.admin.php'));

            Route::middleware(['api', 'throttle:10,1'])
                ->prefix('api/public')
                ->group(base_path('routes/api.public.php'));

        }
    )
    ->withMiddleware(function (Middleware $middleware) {

        $middleware->trustProxies(at: '*');

        $middleware->api(prepend: [
            SetLocale::class,
        ]);

        $middleware->alias([
            'ability' => CheckForAnyAbility::class,
        ]);

        $middleware->priority([
            Authorize::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*'));

    })->create();
