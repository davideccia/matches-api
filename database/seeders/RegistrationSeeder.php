<?php

namespace Database\Seeders;

use App\Models\Athlete;
use App\Models\Discipline;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\WeightCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RegistrationSeeder extends Seeder
{
    public function run(): void
    {
        if (Registration::withoutGlobalScopes()->count() > 0) {
            return;
        }

        $athletes = Athlete::withoutGlobalScopes()->pluck('id', 'last_name');
        $tournaments = Tournament::withoutGlobalScopes()->pluck('id', 'name');
        $disciplines = Discipline::withoutGlobalScopes()->pluck('id', 'label');
        $weightCategories = WeightCategory::withoutGlobalScopes()->pluck('id', 'label');

        $paidAt = Carbon::now();

        $this->seedTorneo1($athletes, $tournaments, $disciplines, $weightCategories, $paidAt);
        $this->seedTorneo2($athletes, $tournaments, $disciplines, $weightCategories, $paidAt);
    }

    /** @param Collection<string,string> $athletes
     * @param  Collection<string,string>  $tournaments
     * @param  Collection<string,string>  $disciplines
     * @param  Collection<string,string>  $weightCategories
     */
    private function seedTorneo1(
        $athletes,
        $tournaments,
        $disciplines,
        $weightCategories,
        Carbon $paidAt,
    ): void {
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

        foreach ($pairs as [$red, $blue, $weightLabel, $disciplineLabel]) {
            $weightCategoryId = $weightCategories[$weightLabel];
            $disciplineId = $disciplines[$disciplineLabel];
            $weightIn = (float) rtrim($weightLabel, 'Kg');

            foreach ([$red, $blue] as $athleteLastName) {
                Registration::create([
                    'athlete_id' => $athletes[$athleteLastName],
                    'tournament_id' => $tournamentId,
                    'discipline_id' => $disciplineId,
                    'weight_category_id' => $weightCategoryId,
                    'paid_at' => $paidAt,
                    'arrived' => true,
                    'weight_in' => $weightIn,
                ]);
            }
        }
    }

    /** @param Collection<string,string> $athletes
     * @param  Collection<string,string>  $tournaments
     * @param  Collection<string,string>  $disciplines
     * @param  Collection<string,string>  $weightCategories
     */
    private function seedTorneo2(
        $athletes,
        $tournaments,
        $disciplines,
        $weightCategories,
        Carbon $paidAt,
    ): void {
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

        foreach ($pairs as [$red, $blue, $weightLabel, $disciplineLabel]) {
            $weightCategoryId = $weightCategories[$weightLabel];
            $disciplineId = $disciplines[$disciplineLabel];
            $weightIn = (float) rtrim($weightLabel, 'Kg');

            foreach ([$red, $blue] as $athleteLastName) {
                Registration::create([
                    'athlete_id' => $athletes[$athleteLastName],
                    'tournament_id' => $tournamentId,
                    'discipline_id' => $disciplineId,
                    'weight_category_id' => $weightCategoryId,
                    'paid_at' => $paidAt,
                    'arrived' => true,
                    'weight_in' => $weightIn,
                ]);
            }
        }
    }
}
