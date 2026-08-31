<?php

namespace App\Models;

use App\Enums\AthleteGenderEnum;
use App\Enums\MatchRecordStatusEnum;
use App\Models\Scopes\AthleteScope;
use App\Observers\AthleteObserver;
use App\Traits\InteractsWithMedia;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy([AthleteObserver::class])]
#[ScopedBy([AthleteScope::class])]
class Athlete extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia;

    public const string PHOTO_MEDIA_COLLECTION_NAME = 'athletes:photo';

    protected $fillable = [
        'first_name',
        'last_name',
        'full_name',
        'birth_date',
        'gender',
        'tax_number',
        'email',
        'team_name',
        'phone_number',
        'generic_match_records_count',
        'registered_match_records_count',
    ];

    protected $appends = [
        'age',
        'is_adult',
        'match_records_count',
    ];

    /**
     * Canonical form of a tax number. Every comparison must go through this:
     * the stored value is normalized by AthleteObserver::saving, so lookups
     * against raw user input would otherwise miss.
     */
    public static function normalizeTaxNumber(?string $taxNumber): string
    {
        return Str::of($taxNumber)->trim()->upper()->value();
    }

    /** Canonical form of an email address. See normalizeTaxNumber(). */
    public static function normalizeEmail(?string $email): string
    {
        return Str::of($email)->trim()->lower()->value();
    }

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'gender' => AthleteGenderEnum::class,
            'generic_match_records_count' => 'integer',
            'registered_match_records_count' => 'integer',
        ];
    }

    protected function isAdult(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->birth_date?->copy()->age >= 18,
        );
    }

    protected function age(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->birth_date?->copy()->age,
        );
    }

    protected function matchRecordsCount(): Attribute
    {
        return Attribute::make(
            get: fn () => (($this->generic_match_records_count ?? 0) + ($this->registered_match_records_count ?? 0)),
        );
    }

    /**
     * Second factor for the public registration form: an Italian tax number is
     * derivable from name, birth date and birthplace, so it cannot stand alone
     * as proof of identity. Compared in constant time.
     */
    public function emailMatches(?string $email): bool
    {
        return hash_equals($this->email ?? '', self::normalizeEmail($email));
    }

    public function syncMatchRecordsCount(): void
    {
        $this->registered_match_records_count = MatchRecord::query()
            ->where(fn (Builder $builder) => $builder
                ->where('red_corner_id', $this->id)
                ->orWhere('blue_corner_id', $this->id)
            )
            ->where('status', MatchRecordStatusEnum::COMPLETED)
            ->count();

        $this->saveQuietly();
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

    public function photoMedia(): MorphOne
    {
        return $this->media()->where('collection_name', self::PHOTO_MEDIA_COLLECTION_NAME)->one();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::PHOTO_MEDIA_COLLECTION_NAME)->singleFile();
    }

    #[Scope]
    public function search(Builder $builder, string $search): Builder
    {
        return $builder->where(fn (Builder $q) => $q
            ->whereLike('full_name', "%{$search}%")
        );
    }

    #[Scope]
    public function adult(Builder $builder, bool $isAdult): Builder
    {
        $cutoffDate = now()->subYears(18)->toDateString();

        return $isAdult
            ? $builder->whereDate('birth_date', '<=', $cutoffDate)
            : $builder->whereDate('birth_date', '>', $cutoffDate);
    }

    #[Scope]
    public function minMatchRecordsCount(Builder $builder, int $minMatchRecordsCount): Builder
    {
        return $builder->whereRaw('(COALESCE(generic_match_records_count, 0) + COALESCE(registered_match_records_count, 0)) >= ?', [$minMatchRecordsCount]);
    }

    #[Scope]
    public function maxMatchRecordsCount(Builder $builder, int $maxMatchRecordsCount): Builder
    {
        return $builder->whereRaw('(COALESCE(generic_match_records_count, 0) + COALESCE(registered_match_records_count, 0)) <= ?', [$maxMatchRecordsCount]);
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
