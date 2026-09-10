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
use Illuminate\Support\Facades\Schema;
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
        'match_records_history',
    ];

    protected $appends = [
        'age',
        'is_adult',
    ];

    public static function normalizeTaxNumber(?string $taxNumber): string
    {
        return Str::of($taxNumber)->trim()->upper()->value();
    }

    public static function normalizeEmail(?string $email): string
    {
        return Str::of($email)->trim()->lower()->value();
    }

    public static function normalizeMatchRecordsHistory(?array $input, ?array $existing): array
    {
        $existingByDisciplineId = collect($existing['disciplines'] ?? [])->keyBy('id');

        $disciplines = collect($input['disciplines'] ?? [])
            ->map(function (array $discipline) use ($existingByDisciplineId): array {
                $manualTotal = (int) ($discipline['manual_total'] ?? 0);
                $appTotal = (int) ($existingByDisciplineId->get($discipline['id'] ?? null)['app_total'] ?? 0);

                return [
                    'id' => $discipline['id'] ?? null,
                    'label' => $discipline['label'] ?? '',
                    'manual_total' => $manualTotal,
                    'app_total' => $appTotal,
                    'total' => $manualTotal + $appTotal,
                ];
            })
            ->values()
            ->all();

        return [
            'total' => array_sum(array_column($disciplines, 'total')),
            'disciplines' => $disciplines,
        ];
    }

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'gender' => AthleteGenderEnum::class,
            'match_records_history' => 'array',
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

    public function matchRecordsCountForDiscipline(?string $disciplineId): int
    {
        $discipline = collect($this->match_records_history['disciplines'] ?? [])
            ->first(fn (array $d) => $d['id'] === $disciplineId);

        return $discipline !== null ? $discipline['total'] : 0;
    }

    public function emailMatches(?string $email): bool
    {
        return hash_equals($this->email ?? '', self::normalizeEmail($email));
    }

    public function syncMatchRecordsHistory(): void
    {
        $appTotals = MatchRecord::query()
            ->where(fn (Builder $builder) => $builder
                ->where('red_corner_id', $this->id)
                ->orWhere('blue_corner_id', $this->id)
            )
            ->where('status', MatchRecordStatusEnum::COMPLETED)
            ->whereNotNull('discipline_id')
            ->selectRaw('discipline_id, COUNT(*) as app_total')
            ->groupBy('discipline_id')
            ->pluck('app_total', 'discipline_id');

        $existingDisciplines = collect($this->match_records_history['disciplines'] ?? []);
        $existingById = $existingDisciplines->filter(fn (array $d) => $d['id'] !== null)->keyBy('id');
        $manualOnly = $existingDisciplines->filter(fn (array $d) => $d['id'] === null);

        $ids = $existingById->keys()->merge($appTotals->keys())->unique()->values();

        $labels = Discipline::query()->whereIn('id', $ids)->pluck('label', 'id');

        $disciplines = $ids
            ->map(function (string $id) use ($existingById, $appTotals, $labels): array {
                $manualTotal = (int) ($existingById->get($id)['manual_total'] ?? 0);
                $appTotal = (int) ($appTotals->get($id) ?? 0);

                return [
                    'id' => $id,
                    'label' => $labels->get($id) ?? ($existingById->get($id)['label'] ?? ''),
                    'manual_total' => $manualTotal,
                    'app_total' => $appTotal,
                    'total' => $manualTotal + $appTotal,
                ];
            })
            ->concat(
                $manualOnly->map(fn (array $d) => [
                    'id' => null,
                    'label' => $d['label'],
                    'manual_total' => (int) ($d['manual_total'] ?? 0),
                    'app_total' => 0,
                    'total' => (int) ($d['manual_total'] ?? 0),
                ])->values()
            )
            ->values();

        $this->match_records_history = [
            'total' => $disciplines->sum('total'),
            'disciplines' => $disciplines->all(),
        ];

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
    public function withoutMatchRecordsHistory(Builder $builder): Builder
    {
        $columns = array_diff(Schema::getColumnListing($this->getTable()), ['match_records_history']);

        return $builder->select($columns);
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
        return $builder->whereRaw("COALESCE((match_records_history->>'total')::int, 0) >= ?", [$minMatchRecordsCount]);
    }

    #[Scope]
    public function maxMatchRecordsCount(Builder $builder, int $maxMatchRecordsCount): Builder
    {
        return $builder->whereRaw("COALESCE((match_records_history->>'total')::int, 0) <= ?", [$maxMatchRecordsCount]);
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
