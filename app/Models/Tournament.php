<?php

namespace App\Models;

use App\Enums\TournamentStatus;
use App\Models\Scopes\TournamentScope;
use App\Observers\TournamentObserver;
use App\Traits\InteractsWithMedia;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy([TournamentObserver::class])]
#[ScopedBy([TournamentScope::class])]
class Tournament extends Model implements HasMedia
{
    use HasUuids, InteractsWithMedia;

    public const string COVER_MEDIA_COLLECTION_NAME = 'tournaments:cover';

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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::COVER_MEDIA_COLLECTION_NAME)->singleFile();
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function matchRecords(): HasMany
    {
        return $this->hasMany(MatchRecord::class)->orderBy('sort');
    }

    public function coverMedia(): MorphOne
    {
        return $this->media()->where('collection_name', self::COVER_MEDIA_COLLECTION_NAME)->one();
    }

    #[Scope]
    public function search(Builder $builder, string $search): Builder
    {
        return $builder;
    }
}
