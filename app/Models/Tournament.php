<?php

namespace App\Models;

use App\Enums\TournamentStatus;
use App\Models\Scopes\TournamentScope;
use App\Observers\TournamentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([TournamentObserver::class])]
#[ScopedBy([TournamentScope::class])]
class Tournament extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'location_name',
        'location_address',
        'location_city',
        'date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => TournamentStatus::class,
        ];
    }
}
