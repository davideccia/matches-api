<?php

use App\Console\Commands\CleanupTemporaryUploadsCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(CleanupTemporaryUploadsCommand::class)->everySixHours()->withoutOverlapping();
Schedule::command('horizon:snapshot')->everyFiveMinutes();

Schedule::command('backup:clean')->daily()->at('01:00')->withoutOverlapping();
Schedule::command('backup:run')->daily()->at('01:30')->withoutOverlapping();
Schedule::command('backup:monitor')->daily()->at('02:00');
