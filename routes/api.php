<?php

use App\Http\Controllers\Api\TireApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:web')->prefix('v1')->group(function () {
    Route::get('/tires', [TireApiController::class, 'tires']);
    Route::get('/tires/{tire}', [TireApiController::class, 'show']);
    Route::get('/tires/{tire}/history', [TireApiController::class, 'history']);
    Route::post('/tires/{tire}/incident', [TireApiController::class, 'incident']);
    Route::post('/tires/{tire}/measurement', [TireApiController::class, 'measurement']);
    Route::post('/tires/{tire}/retire', [TireApiController::class, 'retire']);
    Route::get('/units', [TireApiController::class, 'units']);
    Route::get('/units/{unit}/layout', [TireApiController::class, 'unitLayout']);
    Route::post('/units/{unit}/tire-operations', [TireApiController::class, 'operate']);
});
