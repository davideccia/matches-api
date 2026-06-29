<?php

namespace App\Http\Resources\Public;

use App\Models\Athlete;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Athlete $resource
 */
class PublicRegistrationAthleteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'first_name' => $this->resource->first_name,
            'last_name' => $this->resource->last_name,
            'full_name' => $this->resource->full_name,
            'birth_date' => $this->resource->birth_date?->toDateString(),
            'gender' => $this->resource->gender?->value,
            'team_name' => $this->resource->team_name,
            'default_weight_category_id' => $this->resource->default_weight_category_id,
            'default_discipline_id' => $this->resource->default_discipline_id,
        ];
    }
}
