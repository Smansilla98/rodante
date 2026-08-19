<?php

use App\Http\Controllers\Api\TireApiController;
use App\Http\Controllers\Api\TokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/token', [TokenController::class, 'store'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        Route::delete('/auth/token', [TokenController::class, 'destroy']);
        Route::get('/tires', [TireApiController::class, 'tires']);
        Route::get('/tires/{tire}', [TireApiController::class, 'show']);
        Route::get('/tires/{tire}/history', [TireApiController::class, 'history']);
        Route::get('/units', [TireApiController::class, 'units']);
        Route::get('/units/{unit}/layout', [TireApiController::class, 'unitLayout']);

        Route::post('/tires/{tire}/incident', [TireApiController::class, 'incident'])->middleware('capability:write');
        Route::post('/tires/{tire}/measurement', [TireApiController::class, 'measurement'])->middleware('capability:write');
        Route::post('/tires/{tire}/return-stock', [TireApiController::class, 'returnToStock'])->middleware('capability:write');
        Route::post('/units/{unit}/tire-operations', [TireApiController::class, 'operate'])->middleware('capability:write');
        Route::post('/tires/{tire}/retire', [TireApiController::class, 'retire'])->middleware('capability:retire');
    });
});
