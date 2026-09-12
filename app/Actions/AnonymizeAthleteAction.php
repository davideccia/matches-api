<?php

namespace App\Actions;

use App\Models\Athlete;

class AnonymizeAthleteAction
{
    /**
     * Scrubs an athlete's personal data in place. Registrations and match records are left
     * untouched (deleting the athlete is blocked by AthleteObserver::deleting() anyway once any
     * history exists), so tournament history and statistics survive the athlete's own PII.
     */
    public static function handle(Athlete $athlete): void
    {
        $athlete->first_name = 'Atleta';
        $athlete->last_name = 'Anonimizzato';
        $athlete->tax_number = "ANON-{$athlete->id}";
        $athlete->email = "{$athlete->id}@anonymized.invalid";
        $athlete->phone_number = null;
        $athlete->team_name = null;
        $athlete->birth_date = null;
        $athlete->gender = null;
        $athlete->anonymized_at = now();

        $athlete->clearMediaCollection(Athlete::PHOTO_MEDIA_COLLECTION_NAME);

        $athlete->save();
    }
}
