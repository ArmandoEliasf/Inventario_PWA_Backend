<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BienController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);

    Route::apiResource('bienes', BienController::class)
        ->only(['index', 'show', 'store', 'update', 'destroy'])
        ->parameters(['bienes' => 'bien']);
});
