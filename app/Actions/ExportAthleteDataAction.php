<?php

namespace App\Actions;

use App\Models\Athlete;
use App\Models\MatchRecord;
use App\Models\Registration;

class ExportAthleteDataAction
{
    private const PROFILE_HEADER = [
        'first_name', 'last_name', 'full_name', 'birth_date', 'gender',
        'tax_number', 'email', 'team_name', 'phone_number', 'age', 'is_adult', 'photo_url',
    ];

    private const MATCH_RECORDS_HISTORY_HEADER = ['discipline_label', 'manual_total', 'app_total', 'total'];

    private const REGISTRATION_HEADER = [
        'tournament_name', 'discipline_name', 'weight_category_name', 'paid_at', 'arrived', 'weight_in', 'notes',
    ];

    private const MATCH_HEADER = [
        'role', 'tournament_name', 'discipline_name', 'weight_category_name',
        'opponent_name', 'status', 'end_method', 'sort', 'judges_points',
    ];

    /**
     * @return array<string, mixed>
     */
    private static function profileRow(Athlete $athlete): array
    {
        return [
            'first_name' => $athlete->first_name,
            'last_name' => $athlete->last_name,
            'full_name' => $athlete->full_name,
            'birth_date' => $athlete->birth_date?->toDateString() ?? '',
            'gender' => $athlete->gender?->value ?? '',
            'tax_number' => $athlete->tax_number,
            'email' => $athlete->email,
            'team_name' => $athlete->team_name ?? '',
            'phone_number' => $athlete->phone_number ?? '',
            'age' => $athlete->age ?? '',
            'is_adult' => $athlete->is_adult === null ? '' : (int) $athlete->is_adult,
            'photo_url' => $athlete->photoMedia?->getUrl() ?? '',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function matchRecordsHistoryRows(Athlete $athlete): array
    {
        $history = $athlete->match_records_history ?? [];

        $rows = collect($history['disciplines'] ?? [])
            ->map(fn (array $discipline): array => [
                'discipline_label' => $discipline['label'],
                'manual_total' => $discipline['manual_total'],
                'app_total' => $discipline['app_total'],
                'total' => $discipline['total'],
            ])
            ->all();

        $rows[] = [
            'discipline_label' => 'TOTALE',
            'manual_total' => '',
            'app_total' => '',
            'total' => $history['total'] ?? 0,
        ];

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function registrationRows(Athlete $athlete): array
    {
        return $athlete->registrations
            ->map(fn (Registration $registration): array => [
                'tournament_name' => $registration->tournament?->name ?? '',
                'discipline_name' => $registration->discipline?->label ?? '',
                'weight_category_name' => $registration->weightCategory?->label ?? '',
                'paid_at' => $registration->paid_at?->toDateTimeString() ?? '',
                'arrived' => (int) $registration->arrived,
                'weight_in' => $registration->weight_in ?? '',
                'notes' => $registration->notes ?? '',
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function matchRows(Athlete $athlete): array
    {
        return $athlete->redCornerMatches
            ->map(fn (MatchRecord $match): array => self::matchRow($match, 'red_corner', $match->blueCorner?->full_name))
            ->concat($athlete->blueCornerMatches->map(
                fn (MatchRecord $match): array => self::matchRow($match, 'blue_corner', $match->redCorner?->full_name)
            ))
            ->concat($athlete->wonMatches->map(
                fn (MatchRecord $match): array => self::matchRow(
                    $match,
                    'winner',
                    $match->red_corner_id === $athlete->id ? $match->blueCorner?->full_name : $match->redCorner?->full_name,
                )
            ))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function matchRow(MatchRecord $match, string $role, ?string $opponentName): array
    {
        return [
            'role' => $role,
            'tournament_name' => $match->tournament?->name ?? '',
            'discipline_name' => $match->discipline?->label ?? '',
            'weight_category_name' => $match->weightCategory?->label ?? '',
            'opponent_name' => $opponentName ?? '',
            'status' => $match->status?->value ?? '',
            'end_method' => $match->end_method?->value ?? '',
            'sort' => $match->sort ?? '',
            'judges_points' => $match->judges_points !== null ? json_encode($match->judges_points) : '',
        ];
    }

    /**
     * @return array<string, array{header: array<int, string>, rows: array<int, array<string, mixed>>}>
     */
    public static function handle(Athlete $athlete): array
    {
        $athlete->loadMissing([
            'registrations.tournament',
            'registrations.discipline',
            'registrations.weightCategory',
            'redCornerMatches.tournament',
            'redCornerMatches.discipline',
            'redCornerMatches.weightCategory',
            'redCornerMatches.blueCorner',
            'blueCornerMatches.tournament',
            'blueCornerMatches.discipline',
            'blueCornerMatches.weightCategory',
            'blueCornerMatches.redCorner',
            'wonMatches.tournament',
            'wonMatches.discipline',
            'wonMatches.weightCategory',
            'wonMatches.redCorner',
            'wonMatches.blueCorner',
            'photoMedia',
        ]);

        return [
            'profile' => ['header' => self::PROFILE_HEADER, 'rows' => [self::profileRow($athlete)]],
            'match_records_history' => ['header' => self::MATCH_RECORDS_HISTORY_HEADER, 'rows' => self::matchRecordsHistoryRows($athlete)],
            'registrations' => ['header' => self::REGISTRATION_HEADER, 'rows' => self::registrationRows($athlete)],
            'matches' => ['header' => self::MATCH_HEADER, 'rows' => self::matchRows($athlete)],
        ];
    }
}
