<?php

use App\Console\Commands\CleanupTemporaryUploadsCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(CleanupTemporaryUploadsCommand::class)->everySixHours()->withoutOverlapping();
Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Only schedule backups when the destination disk is actually configured.
// A local-driver disk (the default: storage/backups) always works; a
// non-local disk (e.g. s3-backup) is skipped until it has a bucket, so
// environments without those credentials don't fail every night.
$backupDisk = config('backup.backup.destination.disks')[0] ?? null;
$backupDiskDriver = config("filesystems.disks.{$backupDisk}.driver");
$backupDiskConfigured = $backupDiskDriver === 'local' || filled(config("filesystems.disks.{$backupDisk}.bucket"));

if ($backupDiskConfigured) {
    Schedule::command('backup:clean')->daily()->at('01:00')->withoutOverlapping();
    Schedule::command('backup:run')->cron('30 1 */3 * *')->withoutOverlapping();
    Schedule::command('backup:monitor')->daily()->at('02:00');
}
