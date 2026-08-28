<?php

namespace App\Http\Resources\Public;

use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Tournament $resource
 */
class PublicTournamentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'date_from' => $this->resource->date_from?->toDateString(),
            'date_to' => $this->resource->date_to?->toDateString(),
            'location_city' => $this->resource->location_city,
            'status' => $this->resource->status?->value,
        ];
    }
}
