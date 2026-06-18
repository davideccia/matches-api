<?php

namespace App\Http\Resources;

use App\Models\Athlete;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Athlete $resource
 */
class AthleteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $resourceArray = parent::toArray($request);

        $resourceArray['match_records_count'] = $this->resource->red_corner_matches_count + $this->resource->blue_corner_matches_count;

        return $resourceArray;
    }
}
