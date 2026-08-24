<?php

namespace App\Providers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * The dashboard is protected by HorizonBasicAuth, which authenticates no
     * user. The parameter must therefore be nullable: Laravel's Gate denies an
     * ability to guests unless its first argument accepts null.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', static fn (?Authenticatable $user) => true);
    }

    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }
}
