<?php

namespace App\Models;

use App\Enums\AthleteGenderEnum;
use App\Models\Scopes\AthleteScope;
use App\Observers\AthleteObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([AthleteObserver::class])]
#[ScopedBy([AthleteScope::class])]
class Athlete extends Model
{
    use HasUuids;

    protected $fillable = [
        'first_name',
        'last_name',
        'full_name',
        'birth_date',
        'gender',
        'tax_number',
        'team_name',
        'default_weight_category_id',
        'default_discipline_id',
    ];

    protected $appends = [
        'is_adult',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'gender' => AthleteGenderEnum::class,
            'default_weight_category_id' => 'string',
            'default_discipline_id' => 'string',
        ];
    }

    protected function isAdult(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->birth_date?->age >= 18,
        );
    }

    public function defaultWeightCategory(): BelongsTo
    {
        return $this->belongsTo(WeightCategory::class, 'default_weight_category_id');
    }

    public function defaultDiscipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class, 'default_discipline_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function redCornerMatches(): HasMany
    {
        return $this->hasMany(MatchRecord::class, 'red_corner_id');
    }

    public function blueCornerMatches(): HasMany
    {
        return $this->hasMany(MatchRecord::class, 'blue_corner_id');
    }

    public function wonMatches(): HasMany
    {
        return $this->hasMany(MatchRecord::class, 'winner_id');
    }

    #[Scope]
    public function search(Builder $builder, string $search): Builder
    {
        return $builder->where(fn (Builder $q) => $q
            ->where('full_name', 'ilike', "%{$search}%")
        );
    }

    #[Scope]
    public function inTournament(Builder $builder, string $tournamentId, ?string $disciplineId, ?string $weightCategoryId): Builder
    {
        $registrationsAthletes = Registration::query()
            ->where('tournament_id', $tournamentId)
            ->when($disciplineId, fn (Builder $q) => $q->where('discipline_id', $disciplineId))
            ->when($weightCategoryId, fn (Builder $q) => $q->where('weight_category_id', $weightCategoryId))
            ->select('registrations.athlete_id');

        return $builder->whereIn('id', $registrationsAthletes);
    }
}
