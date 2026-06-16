<?php

use App\Http\Controllers\Api\AthleteController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DisciplineController;
use App\Http\Controllers\Api\MatchRecordController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\TournamentController;
use App\Http\Controllers\Api\TournamentMatchController;
use App\Http\Controllers\Api\TournamentRegistrationController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WeightCategoryController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {

    Route::middleware(['throttle:10,1'])->group(function () {

        Route::get('auth/user', [AuthController::class, 'user']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

    });

    Route::apiResource('users', UserController::class);

    Route::apiResource('weight_categories', WeightCategoryController::class);

    Route::apiResource('disciplines', DisciplineController::class);

    Route::apiResource('athletes', AthleteController::class);

    Route::apiResource('tournaments', TournamentController::class);
    Route::apiResource('tournaments.registrations', TournamentRegistrationController::class)->only(['index', 'store']);
    Route::apiResource('tournaments.match_records', TournamentMatchController::class)->only(['index', 'store']);

    Route::apiResource('registrations', RegistrationController::class);

    Route::apiResource('match_records', MatchRecordController::class);

});
