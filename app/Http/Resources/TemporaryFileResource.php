<?php

namespace App\Http\Resources;

use App\Support\TemporaryFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TemporaryFile */
class TemporaryFileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->originalName,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
        ];
    }
}
