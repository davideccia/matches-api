<?php

namespace App\Models;

use App\Observers\ExperienceTierObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([ExperienceTierObserver::class])]
class ExperienceTier extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tournament_id',
        'label',
        'min_match_count',
        'max_match_count',
        'enabled',
    ];

    protected $attributes = [
        'enabled' => false,
    ];

    protected function casts(): array
    {
        return [
            'min_match_count' => 'integer',
            'max_match_count' => 'integer',
            'enabled' => 'boolean',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    #[Scope]
    public function search(Builder $builder, string $search): Builder
    {
        return $builder->where(fn (Builder $q) => $q
            ->whereLike('label', "%{$search}%")
        );
    }

    #[Scope]
    public function enabled(Builder $builder, bool $enabled = true): Builder
    {
        return $builder->where('enabled', $enabled);
    }

    public function overlapsAnotherTier(): bool
    {
        if (! $this->enabled) {
            return false;
        }

        return static::query()
            ->enabled()
            ->when($this->tournament_id === null,
                fn (Builder $q) => $q->whereNull('tournament_id'),
                fn (Builder $q) => $q->where('tournament_id', $this->tournament_id),
            )
            ->when($this->max_match_count !== null, fn (Builder $q) => $q->where('min_match_count', '<=', $this->max_match_count))
            ->where(fn (Builder $q) => $q
                ->whereNull('max_match_count')
                ->orWhere('max_match_count', '>=', $this->min_match_count)
            )
            ->when($this->exists, fn (Builder $q) => $q->whereKeyNot($this->getKey()))
            ->exists();
    }

    public function contains(int $matchCount): bool
    {
        return
            $matchCount >= $this->min_match_count && ($this->max_match_count === null
                ||
            $matchCount <= $this->max_match_count);
    }
}
