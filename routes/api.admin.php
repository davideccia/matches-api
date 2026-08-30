<?php

use App\Http\Controllers\Api\AthleteController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DisciplineController;
use App\Http\Controllers\Api\ExperienceTierController;
use App\Http\Controllers\Api\MatchRecordController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\TemporaryUploadController;
use App\Http\Controllers\Api\TournamentController;
use App\Http\Controllers\Api\TournamentDisciplineController;
use App\Http\Controllers\Api\TournamentExperienceTierController;
use App\Http\Controllers\Api\TournamentMatchRecordController;
use App\Http\Controllers\Api\TournamentRegistrationController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WeightCategoryController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');

Route::middleware('throttle:auth-password-reset')->group(function () {
    Route::post('auth/forgot_password', [AuthController::class, 'forgotPassword']);
    Route::post('auth/reset_password', [AuthController::class, 'resetPassword']);
});

Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('dashboard', [DashboardController::class, 'index']);

    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::middleware(['throttle:10,1'])->group(function () {

        Route::get('auth/user', [AuthController::class, 'user']);

    });

    Route::delete('users/bulk', [UserController::class, 'bulkDestroy']);
    Route::apiResource('users', UserController::class);

    Route::delete('weight_categories/bulk', [WeightCategoryController::class, 'bulkDestroy']);
    Route::apiResource('weight_categories', WeightCategoryController::class);

    Route::delete('disciplines/bulk', [DisciplineController::class, 'bulkDestroy']);
    Route::apiResource('disciplines', DisciplineController::class);

    Route::delete('experience_tiers/bulk', [ExperienceTierController::class, 'bulkDestroy']);
    Route::apiResource('experience_tiers', ExperienceTierController::class);

    Route::delete('athletes/bulk', [AthleteController::class, 'bulkDestroy']);
    Route::apiResource('athletes', AthleteController::class);

    Route::get('tournaments/{tournament}/match_records/pdf', [TournamentMatchRecordController::class, 'matchRecordsPdf']);
    Route::post('tournaments/{tournament}/match_records/generate', [TournamentMatchRecordController::class, 'generateMatchRecords']);
    Route::delete('tournaments/bulk', [TournamentController::class, 'bulkDestroy']);
    Route::apiResource('tournaments', TournamentController::class);
    Route::apiResource('tournaments.registrations', TournamentRegistrationController::class)->only(['index', 'store']);
    Route::apiResource('tournaments.match_records', TournamentMatchRecordController::class)->only(['index', 'store']);
    Route::apiResource('tournaments.experience_tiers', TournamentExperienceTierController::class)->only(['index', 'store']);
    Route::apiResource('tournaments.disciplines', TournamentDisciplineController::class)->only(['index']);

    Route::delete('registrations/bulk', [RegistrationController::class, 'bulkDestroy']);
    Route::apiResource('registrations', RegistrationController::class);
    Route::get('registrations/{registration}/pdf', [RegistrationController::class, 'pdf']);

    Route::delete('match_records/bulk', [MatchRecordController::class, 'bulkDestroy']);
    Route::apiResource('match_records', MatchRecordController::class);

    Route::post('temporary_uploads', [TemporaryUploadController::class, 'store']);

});
