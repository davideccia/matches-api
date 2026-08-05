<?php

namespace App\Models;

use App\Enums\TournamentStatusEnum;
use App\Models\Scopes\TournamentScope;
use App\Observers\TournamentObserver;
use App\Services\MatchmakingService;
use App\Traits\InteractsWithMedia;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy([TournamentObserver::class])]
#[ScopedBy([TournamentScope::class])]
class Tournament extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia;

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
            'status' => TournamentStatusEnum::class,
            'matchmaking_issues' => 'array',
        ];
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class)->orderByDesc('created_at')->orderBy('id');
    }

    public function matchRecords(): HasMany
    {
        return $this->hasMany(MatchRecord::class)->orderBy('sort')->orderBy('id');
    }

    public function experienceTiers(): HasMany
    {
        return $this->hasMany(ExperienceTier::class)->orderBy('min_match_count')->orderBy('id');
    }

    public function coverMedia(): MorphOne
    {
        return $this->media()->where('collection_name', self::COVER_MEDIA_COLLECTION_NAME)->one();
    }

    #[Scope]
    public function search(Builder $builder, string $search): Builder
    {
        return $builder->where(fn (Builder $q) => $q
            ->whereLike('name', "%{$search}%", caseSensitive: false)
        );
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::COVER_MEDIA_COLLECTION_NAME)->singleFile();
    }

    public function syncMatchmakingIssues(): void
    {
        $this->matchmaking_issues = (new MatchmakingService($this))->getMatchmakingIssues();
        $this->saveQuietly();
    }

    public function runMatchmaking(): void
    {
        $service = new MatchmakingService($this);

        DB::transaction(function () use ($service): void {

            foreach ($service->generateMatchRecords() as $attributes) {
                MatchRecord::create($attributes);
            }

            $this->matchmaking_issues = $service->getMatchmakingIssues();
            $this->saveQuietly();
        });
    }
}
