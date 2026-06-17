<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property Media $resource
 */
class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $resourceArray = parent::toArray($request);

        //

        return $resourceArray;
    }
}
