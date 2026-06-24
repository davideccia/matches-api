<?php

namespace App\Models;

use App\Models\Scopes\RegistrationScope;
use App\Observers\RegistrationObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([RegistrationObserver::class])]
#[ScopedBy([RegistrationScope::class])]
class Registration extends Model
{
    use HasFactory, HasUuids;

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
            'weight_in' => 'float',
            'arrived' => 'boolean',
        ];
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

    #[Scope]
    public function search(Builder $builder, string $search): Builder
    {
        return $builder->where(function (Builder $query) use ($search): void {
            $query->whereHas('athlete', fn (Builder $q) => $q->whereLike('full_name', "%{$search}%"))
                ->orWhereHas('tournament', fn (Builder $q) => $q->whereLike('name', "%{$search}%"))
                ->orWhereHas('discipline', fn (Builder $q) => $q->whereLike('label', "%{$search}%"))
                ->orWhereHas('weightCategory', fn (Builder $q) => $q->whereLike('label', "%{$search}%"));
        });
    }

    #[Scope]
    public function unpaid(Builder $query, bool $value): Builder
    {
        return $value ? $query->whereNull('paid_at') : $query->whereNotNull('paid_at');
    }

    #[Scope]
    public function unarrived(Builder $query, bool $value): Builder
    {
        return $query->where('arrived', ! $value);
    }

    #[Scope]
    public function weightInExceeded(Builder $query, bool $value): Builder
    {
        $operator = $value ? '>' : '<=';

        return $query->whereRaw(
            "weight_in {$operator} (SELECT value FROM weight_categories WHERE weight_categories.id = registrations.weight_category_id)"
        );
    }

    public function isDuplicateRegistration(): bool
    {
        return self::query()
            ->where('athlete_id', $this->athlete_id)
            ->where('tournament_id', $this->tournament_id)
            ->where('discipline_id', $this->discipline_id)
            ->where('weight_category_id', $this->weight_category_id)
            ->when($this->exists, fn ($q) => $q->whereNot('id', $this->id))
            ->exists();
    }

    public function hasMatchRecords(): bool
    {
        return MatchRecord::where(fn (Builder $builder) => $builder
            ->where('red_corner_id', $this->athlete_id)
            ->orWhere('blue_corner_id', $this->athlete_id)
        )
            ->where('tournament_id', $this->tournament_id)
            ->where('discipline_id', $this->discipline_id)
            ->where('weight_category_id', $this->weight_category_id)
            ->exists();
    }
}
