<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    private function definePasswordDefaults(): void
    {
        Password::defaults(function () {
            // Length over composition rules, per NIST 800-63B: mandatory symbol
            // and mixed-case requirements push users toward predictable
            // substitutions without adding real entropy.
            $rule = Password::min(12);

            // uncompromised() calls the haveibeenpwned range API, so it stays out
            // of local and test runs, which must not depend on the network.
            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });
    }

    private function rateLimiting(): void
    {
        foreach (['auth-login', 'auth-password-reset'] as $limiter) {
            RateLimiter::for($limiter, fn (Request $request) => [
                Limit::perMinute(5)->by('ip:'.$request->ip()),
                Limit::perMinute(5)->by('email:'.Str::lower((string) $request->input('email'))),
            ]);
        }

        RateLimiter::for('public-athlete-lookup', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('public-verification-code', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
    }

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->rateLimiting();
        $this->definePasswordDefaults();
    }
}
