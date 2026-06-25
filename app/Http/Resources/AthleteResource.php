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
        return parent::toArray($request);
    }
}
