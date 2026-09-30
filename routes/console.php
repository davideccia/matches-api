<?php

use App\Console\Commands\AnonymizeExpiredAthletesCommand;
use App\Console\Commands\CleanupTemporaryUploadsCommand;
use App\Console\Commands\PruneApiRequestLogsCommand;
use App\Console\Commands\PruneExpiredSessionsCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(CleanupTemporaryUploadsCommand::class)->everySixHours()->withoutOverlapping();
Schedule::command('horizon:snapshot')->everyFiveMinutes();

Schedule::command(AnonymizeExpiredAthletesCommand::class)->daily()->withoutOverlapping();
Schedule::command(PruneExpiredSessionsCommand::class)->daily()->withoutOverlapping();
Schedule::command(PruneApiRequestLogsCommand::class)->cron('0 6 */2 * *')->withoutOverlapping();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('auth:clear-resets')->daily();

if (config('backup.enabled')) {
    Schedule::command('backup:clean')->daily()->at('00:00')->withoutOverlapping();
    Schedule::command('backup:run')->dailyAt('01:00')->withoutOverlapping();
    Schedule::command('backup:monitor')->daily()->at('02:00');
}
