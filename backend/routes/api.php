<?php

use App\Http\Controllers\AulaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BienController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);

    Route::get('aulas', [AulaController::class, 'index']);

    Route::apiResource('bienes', BienController::class)
        ->only(['index', 'show', 'store', 'update', 'destroy'])
        ->parameters(['bienes' => 'bien']);

    Route::put('bienes/{bien}/restaurar', [BienController::class, 'restaurar']);
    Route::delete('bienes/{bien}/purgar', [BienController::class, 'purgar']);
});
