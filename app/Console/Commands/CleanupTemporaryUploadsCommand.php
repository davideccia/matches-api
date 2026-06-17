<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('app:cleanup-temporary-uploads {--minutes=60 : Delete files older than this many minutes}')]
#[Description('Delete expired temporary upload files from storage')]
class CleanupTemporaryUploadsCommand extends Command
{
    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $disk = Storage::disk('local');
        $files = $disk->files('temp');
        $deleted = 0;

        foreach ($files as $file) {
            if ($disk->lastModified($file) < now()->subMinutes($minutes)->timestamp) {
                $disk->delete($file);
                $deleted++;
            }
        }

        $this->info("Deleted {$deleted} temporary file(s).");

        return self::SUCCESS;
    }
}
