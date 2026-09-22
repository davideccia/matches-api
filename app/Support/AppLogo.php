<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AppLogo
{
    private const string DIRECTORY = 'settings';

    private const string BASENAME = 'logo';

    public static function clear(): void
    {
        foreach (Storage::disk('public')->files(self::DIRECTORY) as $existing) {
            if (str_starts_with(basename($existing), self::BASENAME.'.')) {
                Storage::disk('public')->delete($existing);
            }
        }
    }

    public static function path(): ?string
    {
        foreach (Storage::disk('public')->files(self::DIRECTORY) as $existing) {
            if (str_starts_with(basename($existing), self::BASENAME.'.')) {
                return $existing;
            }
        }

        return null;
    }

    public static function store(UploadedFile $file): string
    {
        self::clear();

        return $file->storePubliclyAs(self::DIRECTORY, self::BASENAME.'.'.$file->extension(), 'public');
    }
}
