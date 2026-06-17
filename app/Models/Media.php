<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;

class Media extends \Spatie\MediaLibrary\MediaCollections\Models\Media
{
    protected $appends = ['temporary_url'];

    protected function temporaryUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => \Storage::providesTemporaryUrls() ? $this->getTemporaryUrl(now()->addMinutes(5)) : null,
        );
    }
}
