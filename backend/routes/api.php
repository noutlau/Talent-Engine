<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CandidateController;
use App\Http\Controllers\Api\FormOptionsController;
use Illuminate\Support\Facades\Route;

// Public : formulaire de candidature
Route::get('/skills', [FormOptionsController::class, 'skills']);
Route::get('/options', [FormOptionsController::class, 'options']);
Route::post('/candidates', [CandidateController::class, 'store'])->middleware('throttle:applications');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// Responsable : protégé par jeton Sanctum
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/candidates/ranking', [CandidateController::class, 'ranking']);
    Route::get('/candidates/export', [CandidateController::class, 'export']);
    Route::get('/candidates/{id}/pdf', [CandidateController::class, 'pdf'])->whereNumber('id');
    Route::get('/candidates', [CandidateController::class, 'index']);
    Route::get('/candidates/{id}', [CandidateController::class, 'show'])->whereNumber('id');
    Route::patch('/candidates/{id}', [CandidateController::class, 'update'])->whereNumber('id');
});
