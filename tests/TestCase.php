<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Response cache (spatie/laravel-responsecache) must stay off so public
        // endpoint assertions are deterministic across requests.
        config(['responsecache.enabled' => false]);
    }

    /**
     * Authenticate as the given (or a freshly created) user for admin API routes.
     */
    protected function authenticate(?User $user = null): User
    {
        $user ??= User::factory()->create();

        Sanctum::actingAs($user);

        return $user;
    }
}
