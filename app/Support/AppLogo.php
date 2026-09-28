<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AppLogo
{
    private const string DIRECTORY = 'settings';

    private const string BASENAME = 'logo';

    /**
     * Email clients render neither SVG nor WebP reliably (see matches_dashboard/DESIGN.md §7).
     */
    private const array EMAIL_EXTENSIONS = ['png', 'jpg', 'jpeg'];

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

    /**
     * Absolute URL of the logo for email templates, or null when none is set or its format is not email-safe.
     * The modification time busts the image caches of email proxies when the logo is replaced.
     */
    public static function emailUrl(): ?string
    {
        $path = self::path();

        if ($path === null || ! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EMAIL_EXTENSIONS, true)) {
            return null;
        }

        return url('api/public/settings/logo').'?v='.Storage::disk('public')->lastModified($path);
    }

    public static function store(UploadedFile $file): string
    {
        self::clear();

        return $file->storePubliclyAs(self::DIRECTORY, self::BASENAME.'.'.$file->extension(), 'public');
    }
}
