<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
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
}
