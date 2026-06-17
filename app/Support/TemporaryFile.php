<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

readonly class TemporaryFile
{
    public function __construct(
        public string $id,
        public string $disk,
        public string $path,
        public string $originalName,
        public string $mimeType,
        public int $size,
    ) {}

    public function file(): UploadedFile
    {
        return new UploadedFile(\Storage::disk($this->disk)->path($this->path), $this->originalName, $this->mimeType);
    }
}
