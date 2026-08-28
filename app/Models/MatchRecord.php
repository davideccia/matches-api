<?php

namespace App\Models;

use App\Enums\AthleteGenderEnum;
use App\Enums\MatchRecordEndMethodEnum;
use App\Enums\MatchRecordStatusEnum;
use App\Models\Scopes\MatchRecordScope;
use App\Observers\MatchRecordObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([MatchRecordObserver::class])]
#[ScopedBy([MatchRecordScope::class])]
class MatchRecord extends Model
{
    use HasFactory, HasUuids;

    protected $appends = ['unpaired'];

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
            'gender' => AthleteGenderEnum::class,
            'forced' => 'boolean',
            'winner_id' => 'string',
            'end_method' => MatchRecordEndMethodEnum::class,
            'status' => MatchRecordStatusEnum::class,
            'judges_points' => 'array',
        ];
    }

    /**
     * A half bout: one athlete is on the card, still waiting for an opponent.
     * Computed rather than stored, so it can never drift from the corners.
     */
    protected function unpaired(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->red_corner_id === null || $this->blue_corner_id === null,
        );
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function redCorner(): BelongsTo
    {
        return $this->belongsTo(Athlete::class, 'red_corner_id');
    }

    public function blueCorner(): BelongsTo
    {
        return $this->belongsTo(Athlete::class, 'blue_corner_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Athlete::class, 'winner_id');
    }

    public function weightCategory(): BelongsTo
    {
        return $this->belongsTo(WeightCategory::class);
    }

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    #[Scope]
    public function search(Builder $builder, string $search): Builder
    {
        return $builder->where(function (Builder $query) use ($search): void {
            $query->whereHas('redCorner', fn (Builder $q) => $q->whereLike('full_name', "%{$search}%"))
                ->orWhereHas('blueCorner', fn (Builder $q) => $q->whereLike('full_name', "%{$search}%"))
                ->orWhereHas('weightCategory', fn (Builder $q) => $q->whereLike('label', "%{$search}%"))
                ->orWhereHas('discipline', fn (Builder $q) => $q->whereLike('label', "%{$search}%"));
        });
    }
}
