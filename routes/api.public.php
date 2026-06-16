<?php

use App\Http\Controllers\Api\PublicRegistrationFormController;
use Illuminate\Support\Facades\Route;

Route::prefix('registration_form')->group(function () {
    Route::get('athletes/{athlete:tax_number}', [PublicRegistrationFormController::class, 'showAthlete']);
    Route::post('athletes', [PublicRegistrationFormController::class, 'storeAthlete']);
    Route::get('tournaments', [PublicRegistrationFormController::class, 'tournamentsIndex']);
    Route::get('disciplines', [PublicRegistrationFormController::class, 'disciplinesIndex']);
    Route::get('weight_categories', [PublicRegistrationFormController::class, 'weightCategoriesIndex']);
    Route::post('registrations', [PublicRegistrationFormController::class, 'storeRegistration']);
    Route::get('registrations/{registration}/pdf', [PublicRegistrationFormController::class, 'registrationPdf']);
});
