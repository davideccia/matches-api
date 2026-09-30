<?php

namespace Tests\Feature\Commands;

use App\Models\ApiRequestLog;
use Tests\TestCase;

class PruneApiRequestLogsCommandTest extends TestCase
{
    public function test_it_deletes_only_rows_older_than_the_configured_retention(): void
    {
        config(['logging.api_request_log_days' => 30]);
        $old = ApiRequestLog::factory()->create(['created_at' => now()->subDays(31)]);
        $fresh = ApiRequestLog::factory()->create(['created_at' => now()->subDays(29)]);

        $this->artisan('app:prune-api-request-logs')->assertExitCode(0);

        $this->assertModelMissing($old);
        $this->assertModelExists($fresh);
    }

    public function test_days_option_overrides_the_configured_retention(): void
    {
        config(['logging.api_request_log_days' => 90]);
        $old = ApiRequestLog::factory()->create(['created_at' => now()->subDays(8)]);
        $fresh = ApiRequestLog::factory()->create(['created_at' => now()->subDays(6)]);

        $this->artisan('app:prune-api-request-logs --days=7')->assertExitCode(0);

        $this->assertModelMissing($old);
        $this->assertModelExists($fresh);
    }

    public function test_it_deletes_across_multiple_batches(): void
    {
        ApiRequestLog::factory()->count(1005)->create(['created_at' => now()->subDays(200)]);

        $this->artisan('app:prune-api-request-logs')->assertExitCode(0);

        $this->assertDatabaseCount('api_request_logs', 0);
    }

    public function test_it_rejects_a_non_positive_days_option(): void
    {
        $row = ApiRequestLog::factory()->create(['created_at' => now()->subDays(500)]);

        $this->artisan('app:prune-api-request-logs --days=0')->assertExitCode(1);
        $this->artisan('app:prune-api-request-logs --days=abc')->assertExitCode(1);

        $this->assertModelExists($row);
    }
}
