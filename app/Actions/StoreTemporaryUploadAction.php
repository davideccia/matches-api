<?php

namespace App\Actions;

use App\Support\TemporaryFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StoreTemporaryUploadAction
{
    public static function handle(UploadedFile $file, int $ttlMinutes = 60): TemporaryFile
    {
        $id = Str::orderedUuid()->toString();
        $disk = 'local';
        $extension = $file->guessExtension() ?? $file->getClientOriginalExtension();
        $path = 'temp/'.($extension ? "{$id}.{$extension}" : $id);

        Storage::disk($disk)->put($path, $file->getContent());

        $temporaryFile = new TemporaryFile(
            id: $id,
            disk: $disk,
            path: $path,
            originalName: $file->getClientOriginalName(),
            mimeType: $file->getMimeType() ?? $file->getClientMimeType(),
            size: $file->getSize(),
        );

        $temporaryFile->put($ttlMinutes);

        return $temporaryFile;
    }
}
