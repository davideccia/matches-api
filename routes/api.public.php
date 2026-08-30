<?php

use App\Http\Controllers\Api\PublicRegistrationFormController;
use App\Http\Controllers\Api\PublicTournamentController;
use Illuminate\Support\Facades\Route;

Route::prefix('registration_form')->group(function () {

    // Named limiters (see AppServiceProvider): they stack on top of the
    // group-level throttle:10,1 from bootstrap/app.php but keep their own
    // counter, which an unnamed `throttle:5,1` would not.
    Route::post('athletes/lookup', [PublicRegistrationFormController::class, 'lookupAthlete'])->middleware('throttle:public-athlete-lookup');

    Route::post('verification_code', [PublicRegistrationFormController::class, 'requestVerificationCode'])->middleware('throttle:public-verification-code');

    Route::get('tournaments', [PublicRegistrationFormController::class, 'tournamentsIndex']);

    Route::get('tournaments/{tournament}/disciplines', [PublicRegistrationFormController::class, 'tournamentDisciplinesIndex']);

    Route::get('weight_categories', [PublicRegistrationFormController::class, 'weightCategoriesIndex']);

    Route::post('registrations', [PublicRegistrationFormController::class, 'storeRegistration']);
    Route::get('registrations/{registration}/pdf', [PublicRegistrationFormController::class, 'registrationPdf'])->middleware('signed')->name('public.registration_form.registrations.pdf');

});

Route::prefix('tournaments')->group(function () {

    Route::get('/', [PublicTournamentController::class, 'tournamentsIndex']);
    Route::get('{tournament}/match_records', [PublicTournamentController::class, 'tournamentMatchRecords']);

});
