<?php

namespace App\Http\Resources\Public;

use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * @property Registration $resource
 */
class PublicRegistrationResource extends JsonResource
{
    private const int PDF_URL_TTL_MINUTES = 60;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'tournament' => $this->whenLoaded('tournament', fn () => [
                'id' => $this->resource->tournament->id,
                'name' => $this->resource->tournament->name,
            ]),
            'discipline' => $this->whenLoaded('discipline', fn () => [
                'id' => $this->resource->discipline->id,
                'label' => $this->resource->discipline->label,
            ]),
            'weight_category' => $this->whenLoaded('weightCategory', fn () => [
                'id' => $this->resource->weightCategory->id,
                'label' => $this->resource->weightCategory->label,
            ]),
            'pdf_url' => URL::temporarySignedRoute(
                'public.registration_form.registrations.pdf',
                now()->addMinutes(self::PDF_URL_TTL_MINUTES),
                ['registration' => $this->resource->id],
            ),
        ];
    }
}
