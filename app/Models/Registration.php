<?php

namespace App\Models;

use App\Models\Scopes\RegistrationScope;
use App\Observers\RegistrationObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([RegistrationObserver::class])]
#[ScopedBy([RegistrationScope::class])]
class Registration extends Model
{
    use HasUuids;

    protected $fillable = [
        'athlete_id',
        'tournament_id',
        'discipline_id',
        'weight_category_id',
        'paid_at',
        'arrived',
        'weight_in',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'athlete_id' => 'string',
            'tournament_id' => 'string',
            'discipline_id' => 'string',
            'weight_category_id' => 'string',
            'paid_at' => 'datetime',
            'arrived' => 'boolean',
        ];
    }

    #[Scope]
    public function search(Builder $builder, string $search): Builder
    {
        return $builder;
    }

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    public function weightCategory(): BelongsTo
    {
        return $this->belongsTo(WeightCategory::class);
    }
}
