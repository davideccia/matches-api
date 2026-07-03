<?php

namespace App\Models;

use App\Models\Scopes\DisciplineScope;
use App\Observers\DisciplineObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([DisciplineObserver::class])]
#[ScopedBy([DisciplineScope::class])]
class Discipline extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'label',
        'rounds',
        'minutes_per_round',
    ];

    protected function casts(): array
    {
        return [];
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function matchRecords(): HasMany
    {
        return $this->hasMany(MatchRecord::class);
    }

    #[Scope]
    public function search(Builder $builder, string $search): Builder
    {
        return $builder->where(fn (Builder $q) => $q
            ->whereLike('label', "%{$search}%")
        );
    }
}
