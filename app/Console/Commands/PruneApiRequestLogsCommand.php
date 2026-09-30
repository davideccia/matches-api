<?php

namespace App\Console\Commands;

use App\Models\ApiRequestLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:prune-api-request-logs {--days= : Retention in days (default: logging.api_request_log_days)}')]
#[Description('Delete API request log rows older than the retention window')]
class PruneApiRequestLogsCommand extends Command
{
    private const BATCH_SIZE = 1000;

    public function handle(): int
    {
        $days = filter_var(
            $this->option('days') ?? config('logging.api_request_log_days'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        if ($days === false) {
            $this->error('--days must be a positive integer.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $deleted = 0;

        while (($ids = ApiRequestLog::query()->olderThan($cutoff)->limit(self::BATCH_SIZE)->pluck('id'))->isNotEmpty()) {
            $deleted += ApiRequestLog::whereIn('id', $ids)->delete();
        }

        $this->info("Pruned {$deleted} API request log row(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
