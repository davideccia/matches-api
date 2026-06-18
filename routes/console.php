<?php

use App\Console\Commands\CleanupTemporaryUploadsCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(CleanupTemporaryUploadsCommand::class)->everySixHours()->withoutOverlapping();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
