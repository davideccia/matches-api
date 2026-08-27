<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('public-athlete-lookup', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('public-verification-code', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
    }
}
