<?php

namespace App\Traits;

use App\Support\TemporaryFile;

trait InteractsWithMedia
{
    use \Spatie\MediaLibrary\InteractsWithMedia;

    public function addMediaFromTemporaryFile(string $temporaryFileId, string $mediaCollection): void
    {
        /** @var ?TemporaryFile $temporaryFile */
        $temporaryFile = cache()->get($temporaryFileId);

        if ($temporaryFile === null) {
            return;
        }

        $this->addMedia($temporaryFile->file())->toMediaCollection($mediaCollection);
    }
}
