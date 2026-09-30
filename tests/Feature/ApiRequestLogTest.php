<?php

namespace Tests\Feature;

use App\Http\Middleware\LogApiRequest;
use App\Models\ApiRequestLog;
use App\Models\Athlete;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApiRequestLogTest extends TestCase
{
    public function test_authenticated_request_is_logged_with_user_route_and_status(): void
    {
        $user = $this->authenticate();

        $this->getJson('/api/admin/dashboard')->assertOk();

        $log = ApiRequestLog::sole();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('GET', $log->method);
        $this->assertSame('api/admin/dashboard', $log->path);
        $this->assertSame(200, $log->status);
        $this->assertGreaterThanOrEqual(0, $log->duration_ms);
        $this->assertNotNull($log->created_at);
    }

    public function test_anonymous_request_is_logged_without_user(): void
    {
        $this->getJson('/api/public/registration_form/weight_categories');

        $log = ApiRequestLog::sole();
        $this->assertNull($log->user_id);
        $this->assertSame('api/public/registration_form/weight_categories', $log->path);
    }

    public function test_client_and_server_errors_are_logged(): void
    {
        $this->getJson('/api/admin/dashboard')->assertUnauthorized();

        $this->assertDatabaseHas('api_request_logs', ['path' => 'api/admin/dashboard', 'status' => 401]);
    }

    public function test_path_is_the_route_template_not_the_concrete_url(): void
    {
        $this->authenticate();
        $athlete = Athlete::factory()->create();

        $this->getJson("/api/admin/athletes/{$athlete->id}?secret=1")->assertOk();

        $log = ApiRequestLog::sole();
        $this->assertSame('api/admin/athletes/{athlete}', $log->path);
        $this->assertStringNotContainsString($athlete->id, $log->path);
        $this->assertStringNotContainsString('secret', $log->path);
    }

    public function test_table_has_no_personal_data_columns(): void
    {
        foreach (['ip', 'ip_address', 'user_agent', 'body', 'payload', 'query'] as $column) {
            $this->assertFalse(Schema::hasColumn('api_request_logs', $column), "Unexpected column {$column}");
        }
    }

    public function test_unmatched_route_is_not_logged(): void
    {
        $this->getJson('/api/admin/does-not-exist')->assertNotFound();

        $this->assertDatabaseCount('api_request_logs', 0);
    }

    public function test_public_logo_route_is_excluded(): void
    {
        $this->getJson('/api/public/settings/logo');

        $this->assertDatabaseMissing('api_request_logs', ['path' => 'api/public/settings/logo']);
    }

    public function test_reading_the_log_endpoint_is_not_logged(): void
    {
        $this->authenticate(User::factory()->superadmin()->create());

        $this->getJson('/api/admin/api_request_logs')->assertOk();

        $this->assertDatabaseCount('api_request_logs', 0);
    }

    public function test_failure_to_persist_never_breaks_the_response(): void
    {
        $this->authenticate();
        Schema::drop('api_request_logs');

        $this->getJson('/api/admin/dashboard')->assertOk();
    }

    public function test_middleware_is_registered_on_the_api_group(): void
    {
        $this->assertContains(LogApiRequest::class, app('router')->getMiddlewareGroups()['api']);
    }

    public function test_user_deletion_keeps_the_log_row_with_null_user(): void
    {
        $user = User::factory()->create();
        $log = ApiRequestLog::factory()->create(['user_id' => $user->id]);

        $user->delete();

        $this->assertNull($log->fresh()->user_id);
    }
}
