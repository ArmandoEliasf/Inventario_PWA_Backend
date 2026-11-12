<?php

use App\Http\Controllers\BienController;
use Illuminate\Support\Facades\Route;

Route::apiResource('bienes', BienController::class)
    ->only(['index', 'show', 'store', 'update', 'destroy'])
    ->parameters(['bienes' => 'bien']);
