<?php

namespace Database\Seeders;

use App\Enums\MatchRecordEndMethodEnum;
use App\Enums\MatchRecordStatusEnum;
use App\Models\Athlete;
use App\Models\Discipline;
use App\Models\MatchRecord;
use App\Models\Tournament;
use App\Models\WeightCategory;
use Illuminate\Database\Seeder;

class MatchRecordSeeder extends Seeder
{
    private const JP_A = [
        ['round' => 1, 'judge1_red' => 10, 'judge1_blue' => 9, 'judge2_red' => 10, 'judge2_blue' => 9, 'judge3_red' => 10, 'judge3_blue' => 9],
        ['round' => 2, 'judge1_red' => 10, 'judge1_blue' => 10, 'judge2_red' => 9, 'judge2_blue' => 9, 'judge3_red' => 9, 'judge3_blue' => 10],
        ['round' => 3, 'judge1_red' => 9, 'judge1_blue' => 10, 'judge2_red' => 10, 'judge2_blue' => 10, 'judge3_red' => 9, 'judge3_blue' => 9],
    ];

    private const JP_B = [
        ['round' => 1, 'judge1_red' => 10, 'judge1_blue' => 10, 'judge2_red' => 10, 'judge2_blue' => 9, 'judge3_red' => 9, 'judge3_blue' => 9],
        ['round' => 2, 'judge1_red' => 10, 'judge1_blue' => 10, 'judge2_red' => null, 'judge2_blue' => 9, 'judge3_red' => 9, 'judge3_blue' => null],
        ['round' => 3, 'judge1_red' => null, 'judge1_blue' => null, 'judge2_red' => null, 'judge2_blue' => null, 'judge3_red' => null, 'judge3_blue' => null],
    ];

    private const JP_C = [
        ['round' => 1, 'judge1_red' => 10, 'judge1_blue' => 10, 'judge2_red' => null, 'judge2_blue' => 9, 'judge3_red' => 9, 'judge3_blue' => null],
        ['round' => 2, 'judge1_red' => null, 'judge1_blue' => null, 'judge2_red' => null, 'judge2_blue' => null, 'judge3_red' => null, 'judge3_blue' => null],
        ['round' => 3, 'judge1_red' => null, 'judge1_blue' => null, 'judge2_red' => null, 'judge2_blue' => null, 'judge3_red' => null, 'judge3_blue' => null],
    ];

    public function run(): void
    {
        if (MatchRecord::withoutGlobalScopes()->count() > 0) {
            return;
        }

        $athleteMap = Athlete::withoutGlobalScopes()->get(['id', 'last_name', 'team_name', 'gender'])
            ->keyBy('last_name');
        $tournaments = Tournament::withoutGlobalScopes()->pluck('id', 'name');
        $disciplines = Discipline::withoutGlobalScopes()->pluck('id', 'label');
        $weightCategories = WeightCategory::withoutGlobalScopes()->pluck('id', 'label');

        $this->seedTorneo1($athleteMap, $tournaments, $disciplines, $weightCategories);
        $this->seedTorneo2($athleteMap, $tournaments, $disciplines, $weightCategories);
    }

    private function seedTorneo1($athleteMap, $tournaments, $disciplines, $weightCategories): void
    {
        $tournamentId = $tournaments['Torneo 1'];

        // [red, blue, weight label, discipline label]
        $pairs = [
            ['M071', 'M072', '66Kg', 'MMA A'],
            ['M073', 'M074', '68Kg', 'K1 Full'],
            ['M075', 'M076', '70Kg', 'MMA D'],
            ['M077', 'M078', '73Kg', 'Muay Thai Light'],
            ['M079', 'M080', '80Kg', 'K1 Full'],
            ['F071', 'F072', '66Kg', 'K1 Light'],
            ['F073', 'F074', '68Kg', 'MMA A'],
            ['F075', 'F076', '70Kg', 'MMA B'],
            ['F077', 'F078', '73Kg', 'MMA D'],
            ['F079', 'F080', '80Kg', 'Muay Thai Full'],
        ];

        $endMethods = [
            MatchRecordEndMethodEnum::VICTORY_KO,
            MatchRecordEndMethodEnum::VICTORY_UNANIMOUS_DECISION,
            MatchRecordEndMethodEnum::VICTORY_TKO,
            MatchRecordEndMethodEnum::VICTORY_SPLIT_DECISION,
            MatchRecordEndMethodEnum::VICTORY_DISQUALIFICATION,
        ];

        $completedCount = 0;

        foreach ($pairs as $i => [$red, $blue, $weightLabel, $disciplineLabel]) {
            $redAthlete = $athleteMap[$red];
            $blueAthlete = $athleteMap[$blue];

            $data = [
                'tournament_id' => $tournamentId,
                'red_corner_id' => $redAthlete->id,
                'blue_corner_id' => $blueAthlete->id,
                'weight_category_id' => $weightCategories[$weightLabel],
                'discipline_id' => $disciplines[$disciplineLabel],
                'gender' => $redAthlete->gender,
                'forced' => true,
                'red_corner_team' => $redAthlete->team_name,
                'blue_corner_team' => $blueAthlete->team_name,
                'sort' => $i + 1,
                'rounds' => 3,
                'minutes_per_round' => 3.0,
            ];

            if ($i % 2 === 0) {
                $endMethod = $endMethods[$completedCount];
                $winnerId = $completedCount % 2 === 0 ? $redAthlete->id : $blueAthlete->id;

                $data['status'] = MatchRecordStatusEnum::COMPLETED;
                $data['end_method'] = $endMethod;
                $data['winner_id'] = $winnerId;

                $completedCount++;
            } else {
                $data['status'] = MatchRecordStatusEnum::CANCELLED;
            }

            MatchRecord::create($data);
        }
    }

    private function seedTorneo2($athleteMap, $tournaments, $disciplines, $weightCategories): void
    {
        $tournamentId = $tournaments['Torneo 2'];

        // [red, blue, weight label, discipline label]
        $pairs = [
            // Males 66Kg
            ['M071', 'M072', '66Kg', 'MMA A'],
            ['M001', 'M002', '66Kg', 'MMA A'],
            ['M003', 'M004', '66Kg', 'MMA B'],
            ['M005', 'M006', '66Kg', 'MMA B'],
            ['M007', 'M008', '66Kg', 'K1 Full'],
            ['M009', 'M010', '66Kg', 'K1 Full'],
            ['M011', 'M012', '66Kg', 'Muay Thai Light'],
            ['M013', 'M014', '66Kg', 'Muay Thai Light'],
            // Males 68Kg
            ['M015', 'M016', '68Kg', 'MMA A'],
            ['M017', 'M018', '68Kg', 'MMA A'],
            ['M019', 'M020', '68Kg', 'MMA B'],
            ['M021', 'M022', '68Kg', 'MMA B'],
            ['M073', 'M074', '68Kg', 'K1 Full'],
            ['M023', 'M024', '68Kg', 'K1 Full'],
            ['M025', 'M026', '68Kg', 'Muay Thai Light'],
            ['M027', 'M028', '68Kg', 'Muay Thai Light'],
            // Males 70Kg
            ['M075', 'M076', '70Kg', 'MMA A'],
            ['M029', 'M030', '70Kg', 'MMA A'],
            ['M031', 'M032', '70Kg', 'MMA B'],
            ['M033', 'M034', '70Kg', 'MMA B'],
            ['M035', 'M036', '70Kg', 'K1 Full'],
            ['M037', 'M038', '70Kg', 'K1 Full'],
            ['M039', 'M040', '70Kg', 'Muay Thai Light'],
            ['M041', 'M042', '70Kg', 'Muay Thai Light'],
            // Males 73Kg
            ['M043', 'M044', '73Kg', 'MMA A'],
            ['M045', 'M046', '73Kg', 'MMA A'],
            ['M047', 'M048', '73Kg', 'MMA B'],
            ['M049', 'M050', '73Kg', 'MMA B'],
            ['M051', 'M052', '73Kg', 'K1 Full'],
            ['M053', 'M054', '73Kg', 'K1 Full'],
            ['M077', 'M078', '73Kg', 'Muay Thai Light'],
            ['M055', 'M056', '73Kg', 'Muay Thai Light'],
            // Males 80Kg
            ['M057', 'M058', '80Kg', 'MMA A'],
            ['M059', 'M060', '80Kg', 'MMA A'],
            ['M061', 'M062', '80Kg', 'MMA B'],
            ['M063', 'M064', '80Kg', 'MMA B'],
            ['M079', 'M080', '80Kg', 'K1 Full'],
            ['M065', 'M066', '80Kg', 'K1 Full'],
            ['M067', 'M068', '80Kg', 'Muay Thai Light'],
            ['M069', 'M070', '80Kg', 'Muay Thai Light'],
            // Females 66Kg
            ['F001', 'F002', '66Kg', 'MMA A'],
            ['F003', 'F004', '66Kg', 'MMA A'],
            ['F005', 'F006', '66Kg', 'MMA D'],
            ['F007', 'F008', '66Kg', 'MMA D'],
            ['F071', 'F072', '66Kg', 'K1 Light'],
            ['F009', 'F010', '66Kg', 'K1 Light'],
            ['F011', 'F012', '66Kg', 'Muay Thai Full'],
            ['F013', 'F014', '66Kg', 'Muay Thai Full'],
            // Females 68Kg
            ['F073', 'F074', '68Kg', 'MMA A'],
            ['F015', 'F016', '68Kg', 'MMA A'],
            ['F017', 'F018', '68Kg', 'MMA D'],
            ['F019', 'F020', '68Kg', 'MMA D'],
            ['F021', 'F022', '68Kg', 'K1 Light'],
            ['F023', 'F024', '68Kg', 'K1 Light'],
            ['F025', 'F026', '68Kg', 'Muay Thai Full'],
            ['F027', 'F028', '68Kg', 'Muay Thai Full'],
            // Females 70Kg
            ['F029', 'F030', '70Kg', 'MMA A'],
            ['F031', 'F032', '70Kg', 'MMA A'],
            ['F075', 'F076', '70Kg', 'MMA D'],
            ['F033', 'F034', '70Kg', 'MMA D'],
            ['F035', 'F036', '70Kg', 'K1 Light'],
            ['F037', 'F038', '70Kg', 'K1 Light'],
            ['F039', 'F040', '70Kg', 'Muay Thai Full'],
            ['F041', 'F042', '70Kg', 'Muay Thai Full'],
            // Females 73Kg
            ['F043', 'F044', '73Kg', 'MMA A'],
            ['F045', 'F046', '73Kg', 'MMA A'],
            ['F077', 'F078', '73Kg', 'MMA D'],
            ['F047', 'F048', '73Kg', 'MMA D'],
            ['F049', 'F050', '73Kg', 'K1 Light'],
            ['F051', 'F052', '73Kg', 'K1 Light'],
            ['F053', 'F054', '73Kg', 'Muay Thai Full'],
            ['F055', 'F056', '73Kg', 'Muay Thai Full'],
            // Females 80Kg
            ['F057', 'F058', '80Kg', 'MMA A'],
            ['F059', 'F060', '80Kg', 'MMA A'],
            ['F061', 'F062', '80Kg', 'MMA D'],
            ['F063', 'F064', '80Kg', 'MMA D'],
            ['F065', 'F066', '80Kg', 'K1 Light'],
            ['F067', 'F068', '80Kg', 'K1 Light'],
            ['F079', 'F080', '80Kg', 'Muay Thai Full'],
            ['F069', 'F070', '80Kg', 'Muay Thai Full'],
        ];

        $endMethods = [
            MatchRecordEndMethodEnum::VICTORY_KO,
            MatchRecordEndMethodEnum::VICTORY_UNANIMOUS_DECISION,
            MatchRecordEndMethodEnum::VICTORY_TKO,
            MatchRecordEndMethodEnum::VICTORY_SPLIT_DECISION,
            MatchRecordEndMethodEnum::VICTORY_DISQUALIFICATION,
            MatchRecordEndMethodEnum::DRAW,
        ];

        $judgesPointsPatterns = [self::JP_A, self::JP_B, self::JP_C];

        $completedCount = 0;

        foreach ($pairs as $i => [$red, $blue, $weightLabel, $disciplineLabel]) {
            $redAthlete = $athleteMap[$red];
            $blueAthlete = $athleteMap[$blue];

            $data = [
                'tournament_id' => $tournamentId,
                'red_corner_id' => $redAthlete->id,
                'blue_corner_id' => $blueAthlete->id,
                'weight_category_id' => $weightCategories[$weightLabel],
                'discipline_id' => $disciplines[$disciplineLabel],
                'gender' => $redAthlete->gender,
                'forced' => true,
                'red_corner_team' => $redAthlete->team_name,
                'blue_corner_team' => $blueAthlete->team_name,
                'sort' => $i + 1,
                'rounds' => 3,
                'minutes_per_round' => '03:00',
            ];

            if ($i <= 38) {
                if ($i % 3 !== 2) {
                    $endMethod = $endMethods[$completedCount % 6];
                    $judgesPoints = $judgesPointsPatterns[$completedCount % 3];
                    $winnerId = $endMethod === MatchRecordEndMethodEnum::DRAW
                        ? null
                        : ($completedCount % 2 === 0 ? $redAthlete->id : $blueAthlete->id);

                    $data['status'] = MatchRecordStatusEnum::COMPLETED;
                    $data['end_method'] = $endMethod;
                    $data['winner_id'] = $winnerId;
                    $data['judges_points'] = $judgesPoints;

                    $completedCount++;
                } else {
                    $data['status'] = MatchRecordStatusEnum::CANCELLED;
                }
            } elseif ($i === 39) {
                $data['status'] = MatchRecordStatusEnum::IN_PROGRESS;
            } else {
                $data['status'] = MatchRecordStatusEnum::SCHEDULED;
            }

            MatchRecord::create($data);
        }
    }
}
