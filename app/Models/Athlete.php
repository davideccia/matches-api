<?php

namespace App\Models;

use App\Enums\Gender;
use App\Models\Scopes\AthleteScope;
use App\Observers\AthleteObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([AthleteObserver::class])]
#[ScopedBy([AthleteScope::class])]
class Athlete extends Model
{
    use HasUuids;

    protected $fillable = [
        'first_name',
        'last_name',
        'birth_date',
        'gender',
        'tax_number',
        'team_name',
        'default_weight_category_id',
        'default_discipline_id',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'gender' => Gender::class,
            'default_weight_category_id' => 'string',
            'default_discipline_id' => 'string',
        ];
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
