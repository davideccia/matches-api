<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExperienceTierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $resourceArray = parent::toArray($request);

        //

        return $resourceArray;
    }
}
