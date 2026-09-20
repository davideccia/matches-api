<?php

namespace App\Providers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
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

        // Routes the LongWaitDetected alert declared under horizon.waits. Without
        // this the event fires into the void, which on a single-VPS deployment
        // removes the only signal that the workers have stopped draining the
        // queue. A null address is filtered out by the notification's via(), so
        // leaving HORIZON_NOTIFICATION_EMAIL unset simply disables the alert.
        //
        // The notification is not ShouldQueue: it is sent synchronously and so
        // still goes out while the queue itself is backed up.
        Horizon::routeMailNotificationsTo(config('horizon.notification_email'));
    }
}
