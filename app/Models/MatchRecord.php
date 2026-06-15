<?php

namespace App\Models;

use App\Enums\EndMethod;
use App\Enums\Gender;
use App\Enums\MatchStatus;
use App\Models\Scopes\MatchRecordScope;
use App\Observers\MatchRecordObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([MatchRecordObserver::class])]
#[ScopedBy([MatchRecordScope::class])]
class MatchRecord extends Model
{
    use HasUuids;

    protected $fillable = [
        'tournament_id',
        'red_corner_id',
        'blue_corner_id',
        'weight_category_id',
        'discipline_id',
        'gender',
        'forced',
        'red_corner_team',
        'blue_corner_team',
        'sort',
        'scheduled_time',
        'winner_id',
        'end_round',
        'end_method',
        'status',
        'rounds',
        'minutes_per_round',
        'judges_points',
    ];

    protected function casts(): array
    {
        return [
            'tournament_id' => 'string',
            'red_corner_id' => 'string',
            'blue_corner_id' => 'string',
            'weight_category_id' => 'string',
            'discipline_id' => 'string',
            'gender' => Gender::class,
            'forced' => 'boolean',
            'winner_id' => 'string',
            'end_method' => EndMethod::class,
            'status' => MatchStatus::class,
            'judges_points' => 'array',
        ];
    }

    #[Scope]
    public function search(Builder $builder, string $search): Builder
    {
        return $builder;
    }
}
