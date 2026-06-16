<?php

use App\Http\Controllers\Api\DisciplineController;
use App\Http\Controllers\Api\PublicRegistrationFormController;
use App\Http\Controllers\Api\PublicTournamentController;
use App\Http\Controllers\Api\WeightCategoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('registration_form')->group(function () {

    Route::get('athletes/{athlete:tax_number}', [PublicRegistrationFormController::class, 'showAthlete']);
    Route::post('athletes', [PublicRegistrationFormController::class, 'storeAthlete']);

    Route::get('tournaments', [PublicRegistrationFormController::class, 'tournamentsIndex']);

    Route::get('disciplines', [DisciplineController::class, 'index']);

    Route::get('weight_categories', [WeightCategoryController::class, 'index']);

    Route::post('registrations', [PublicRegistrationFormController::class, 'storeRegistration']);
    Route::get('registrations/{registration}/pdf', [PublicRegistrationFormController::class, 'registrationPdf']);

});

Route::prefix('tournaments')->group(function () {

    Route::get('/', [PublicTournamentController::class, 'tournamentsIndex']);
    Route::get('{tournament}/match_records', [PublicTournamentController::class, 'tournamentMatchRecords']);

});
