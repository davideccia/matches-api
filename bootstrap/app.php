<?php

use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Notifications\ApplicationErrorNotification;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
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

        $middleware->api(append: [
            SecurityHeaders::class,
        ]);

        $middleware->web(append: [
            SecurityHeaders::class,
        ]);

        // Deliberately registered but unused: every token is issued with the
        // default ['*'] abilities, because all admin users are trusted staff
        // (see docs/security-issues/SPECS.md #7 and #8). The alias stays wired
        // so that scoping a future token type is a route change, not a setup.
        $middleware->alias([
            'ability' => CheckForAnyAbility::class,
        ]);

        $middleware->priority([
            Authorize::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*'));

        // Mails the configured address on every reportable exception, in
        // addition to the normal daily log channel. Throttled per exception
        // class+file+line so a repeating error doesn't flood the mailbox.
        $exceptions->report(function (Throwable $e) {

            if (! $address = config('mail.admin_error_address')) {
                return;
            }

            $key = 'error-notified:'.md5($e::class.$e->getFile().$e->getLine());

            if (Cache::add($key, true, now()->addMinutes(15))) {
                Notification::route('mail', $address)->notify(new ApplicationErrorNotification($e));
            }

        });

    })->create();
