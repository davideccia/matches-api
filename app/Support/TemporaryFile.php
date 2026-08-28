<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;

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

    public static function cacheKey(string $id): string
    {
        return 'tmp_upload:'.auth()->id().':'.$id;
    }

    public static function find(string $id): ?self
    {
        $temporaryFile = Cache::get(self::cacheKey($id));

        return $temporaryFile instanceof self ? $temporaryFile : null;
    }

    public function put(int $ttlMinutes): void
    {
        Cache::put(self::cacheKey($this->id), $this, now()->addMinutes($ttlMinutes));
    }

    public function file(): UploadedFile
    {
        return new UploadedFile(\Storage::disk($this->disk)->path($this->path), $this->originalName, $this->mimeType);
    }
}
