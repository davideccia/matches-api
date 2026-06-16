<?php

namespace App\Models;

use App\Enums\TournamentStatus;
use App\Models\Scopes\TournamentScope;
use App\Observers\TournamentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    #[Scope]
    public function search(Builder $builder, string $search): Builder
    {
        return $builder;
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function matchRecords(): HasMany
    {
        return $this->hasMany(MatchRecord::class)->orderBy('sort');
    }
}
