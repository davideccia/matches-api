<?php

namespace App\Models;

use App\Models\Scopes\DisciplineScope;
use App\Observers\DisciplineObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([DisciplineObserver::class])]
#[ScopedBy([DisciplineScope::class])]
class Discipline extends Model
{
    use HasUuids;

    protected $fillable = [
        'label',
    ];

    protected function casts(): array
    {
        return [];
    }

    #[Scope]
    public function search(Builder $builder, string $search): Builder
    {
        return $builder->where(fn (Builder $q) => $q
            ->where('label', 'ilike', "%{$search}%")
        );
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function matchRecords(): HasMany
    {
        return $this->hasMany(MatchRecord::class);
    }

    public function defaultAthletes(): HasMany
    {
        return $this->hasMany(Athlete::class, 'default_discipline_id');
    }
}
