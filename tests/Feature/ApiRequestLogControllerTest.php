<?php

namespace Tests\Feature;

use App\Models\ApiRequestLog;
use App\Models\User;
use Tests\TestCase;

class ApiRequestLogControllerTest extends TestCase
{
    // ---------------------------------------------------------------------
    // index
    // ---------------------------------------------------------------------

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/admin/api_request_logs')->assertUnauthorized();
    }

    public function test_index_is_forbidden_for_non_superadmin(): void
    {
        $this->authenticate();

        $this->getJson('/api/admin/api_request_logs')->assertForbidden();
    }

    public function test_index_returns_logs_newest_first_with_resource_shape_for_superadmin(): void
    {
        $this->authenticate(User::factory()->superadmin()->create());
        $older = ApiRequestLog::factory()->create(['path' => 'api/admin/older', 'created_at' => now()->subHour()]);
        $newer = ApiRequestLog::factory()->create(['path' => 'api/admin/newer', 'created_at' => now()->subMinute()]);

        $response = $this->getJson('/api/admin/api_request_logs?path=api/admin/');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonStructure([
                'data' => [['id', 'user_id', 'method', 'path', 'route_name', 'status', 'duration_ms', 'created_at']],
                'meta' => ['total', 'per_page'],
            ]);
    }

    public function test_index_with_user_exposes_only_id_and_username(): void
    {
        $this->authenticate(User::factory()->superadmin()->create());
        $actor = User::factory()->create();
        ApiRequestLog::factory()->create(['user_id' => $actor->id, 'path' => 'api/admin/with-user']);

        $response = $this->getJson('/api/admin/api_request_logs?with=user&path=with-user');

        $response->assertOk()
            ->assertJsonPath('data.0.user.id', $actor->id)
            ->assertJsonPath('data.0.user.username', $actor->username);

        $this->assertSame(['id', 'username'], array_keys($response->json('data.0.user')));
        $response->assertJsonMissing(['email' => $actor->email]);
    }

    public function test_index_filters_by_user_method_status_status_class_and_path(): void
    {
        $this->authenticate(User::factory()->superadmin()->create());
        $actor = User::factory()->create();
        $match = ApiRequestLog::factory()->create([
            'user_id' => $actor->id,
            'method' => 'POST',
            'status' => 422,
            'path' => 'api/admin/filter-target',
        ]);
        ApiRequestLog::factory()->create(['method' => 'GET', 'status' => 200, 'path' => 'api/admin/filter-other']);

        $this->getJson("/api/admin/api_request_logs?user_id={$actor->id}")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);

        $this->getJson('/api/admin/api_request_logs?method=POST&path=filter-')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);

        $this->getJson('/api/admin/api_request_logs?status=422')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);

        $this->getJson('/api/admin/api_request_logs?status_class=4&path=filter-')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
    }

    public function test_index_filters_by_date_range(): void
    {
        $this->authenticate(User::factory()->superadmin()->create());
        $old = ApiRequestLog::factory()->create(['path' => 'api/admin/dated', 'created_at' => '2026-01-10 12:00:00']);
        ApiRequestLog::factory()->create(['path' => 'api/admin/dated', 'created_at' => '2026-03-10 12:00:00']);

        $this->getJson('/api/admin/api_request_logs?path=dated&date_from=2026-01-01&date_to=2026-02-01')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $old->id);
    }

    public function test_index_path_filter_treats_like_wildcards_literally(): void
    {
        $this->authenticate(User::factory()->superadmin()->create());
        ApiRequestLog::factory()->create(['path' => 'api/admin/anything']);

        $this->getJson('/api/admin/api_request_logs?path=%25')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_index_is_paginated_by_default(): void
    {
        $this->authenticate(User::factory()->superadmin()->create());
        ApiRequestLog::factory()->count(3)->create(['path' => 'api/admin/paged']);

        $this->getJson('/api/admin/api_request_logs?path=paged&per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3);
    }

    public function test_index_validates_filters(): void
    {
        $this->authenticate(User::factory()->superadmin()->create());

        $this->getJson('/api/admin/api_request_logs?user_id=nope&status_class=9&method=get&with=password')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id', 'status_class', 'method', 'with.0']);
    }
}
