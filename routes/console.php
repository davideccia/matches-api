<?php

use App\Console\Commands\CleanupTemporaryUploadsCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(CleanupTemporaryUploadsCommand::class)->everySixHours()->withoutOverlapping();
Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Only schedule backups when the destination disk is actually configured.
// On environments without S3 credentials (e.g. local/staging) the backup
// commands are skipped instead of failing every night.
$backupDisk = config('backup.backup.destination.disks')[0] ?? null;
$backupDiskConfigured = $backupDisk === 'local' || filled(config("filesystems.disks.{$backupDisk}.bucket"));

if ($backupDiskConfigured) {
    Schedule::command('backup:clean')->daily()->at('01:00')->withoutOverlapping();
    Schedule::command('backup:run')->daily()->at('01:30')->withoutOverlapping();
    Schedule::command('backup:monitor')->daily()->at('02:00');
}
