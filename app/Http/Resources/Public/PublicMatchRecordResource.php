<?php

namespace App\Http\Resources\Public;

use App\Models\MatchRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property MatchRecord $resource
 */
class PublicMatchRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'sort' => $this->resource->sort,
            'scheduled_time' => $this->resource->scheduled_time,
            'status' => $this->resource->status?->value,
            'end_method' => $this->resource->end_method?->value,
            'rounds' => $this->resource->rounds,
            'minutes_per_round' => $this->resource->minutes_per_round,
            'red_corner_id' => $this->resource->red_corner_id,
            'blue_corner_id' => $this->resource->blue_corner_id,
            'winner_id' => $this->resource->winner_id,
            'red_corner_team' => $this->resource->red_corner_team,
            'blue_corner_team' => $this->resource->blue_corner_team,
            'unpaired' => $this->resource->unpaired,
            'judges_points' => $this->resource->judges_points,
            'tournament' => $this->whenLoaded('tournament', fn () => new PublicTournamentResource($this->resource->tournament)),
            'red_corner' => $this->whenLoaded('redCorner', fn () => new PublicAthleteResource($this->resource->redCorner)),
            'blue_corner' => $this->whenLoaded('blueCorner', fn () => new PublicAthleteResource($this->resource->blueCorner)),
            'winner' => $this->whenLoaded('winner', fn () => new PublicAthleteResource($this->resource->winner)),
            'weight_category' => $this->whenLoaded('weightCategory', fn () => ['id' => $this->resource->weightCategory->id, 'label' => $this->resource->weightCategory->label]),
            'discipline' => $this->whenLoaded('discipline', fn () => ['id' => $this->resource->discipline->id, 'label' => $this->resource->discipline->label]),
        ];
    }
}
