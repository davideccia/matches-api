<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiRequestLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'method' => $this->method,
            'path' => $this->path,
            'route_name' => $this->route_name,
            'status' => $this->status,
            'duration_ms' => $this->duration_ms,
            'created_at' => $this->created_at,
            'user' => $this->whenLoaded('user', fn () => $this->user === null ? null : [
                'id' => $this->user->id,
                'username' => $this->user->username,
            ]),
        ];
    }
}
