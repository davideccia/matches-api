<?php

use App\Http\Controllers\Api\AthleteController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DisciplineController;
use App\Http\Controllers\Api\MatchRecordController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\TemporaryUploadController;
use App\Http\Controllers\Api\TournamentController;
use App\Http\Controllers\Api\TournamentMatchRecordController;
use App\Http\Controllers\Api\TournamentRegistrationController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WeightCategoryController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/forgot_password', [AuthController::class, 'forgotPassword']);
Route::post('auth/reset_password', [AuthController::class, 'resetPassword']);

Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('dashboard', [DashboardController::class, 'index']);

    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::middleware(['throttle:10,1'])->group(function () {

        Route::get('auth/user', [AuthController::class, 'user']);

    });

    Route::apiResource('users', UserController::class);

    Route::apiResource('weight_categories', WeightCategoryController::class);

    Route::apiResource('disciplines', DisciplineController::class);

    Route::apiResource('athletes', AthleteController::class);

    Route::get('tournaments/{tournament}/match_records/pdf', [TournamentMatchRecordController::class, 'matchRecordsPdf']);
    Route::apiResource('tournaments', TournamentController::class);
    Route::apiResource('tournaments.registrations', TournamentRegistrationController::class)->only(['index', 'store']);
    Route::apiResource('tournaments.match_records', TournamentMatchRecordController::class)->only(['index', 'store']);

    Route::apiResource('registrations', RegistrationController::class);
    Route::get('registrations/{registration}/pdf', [RegistrationController::class, 'pdf']);

    Route::apiResource('match_records', MatchRecordController::class);

    Route::post('temporary_uploads', [TemporaryUploadController::class, 'store']);

});
