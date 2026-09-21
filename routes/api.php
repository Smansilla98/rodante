<?php

use App\Http\Controllers\Api\ApiSurfaceController;
use App\Http\Controllers\Api\TireApiController;
use App\Http\Controllers\Api\TokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/token', [TokenController::class, 'store'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        Route::delete('/auth/token', [TokenController::class, 'destroy']);
        Route::get('/me', [ApiSurfaceController::class, 'me']);
        Route::get('/dashboard', [ApiSurfaceController::class, 'dashboard']);
        Route::get('/bases', [ApiSurfaceController::class, 'bases']);
        Route::get('/movement-reasons', [ApiSurfaceController::class, 'movementReasons']);
        Route::get('/retread-shops', [ApiSurfaceController::class, 'retreadShops']);
        Route::get('/tire-catalog', [ApiSurfaceController::class, 'tireCatalog']);
        Route::get('/work-orders', [ApiSurfaceController::class, 'workOrders']);
        Route::get('/work-orders/{workOrder}', [ApiSurfaceController::class, 'showWorkOrder']);
        Route::post('/work-orders/{workOrder}/send', [ApiSurfaceController::class, 'sendWorkOrderToShop'])->middleware('capability:write');
        Route::post('/work-orders/{workOrder}/close', [ApiSurfaceController::class, 'closeWorkOrder'])->middleware('capability:write');
        Route::post('/work-orders/{workOrder}/cancel', [ApiSurfaceController::class, 'cancelWorkOrder'])->middleware('capability:write');
        Route::get('/inventory-sessions', [ApiSurfaceController::class, 'inventorySessions']);
        Route::post('/inventory-sessions', [ApiSurfaceController::class, 'storeInventorySession'])->middleware('capability:write');
        Route::get('/inventory-sessions/{inventory}', [ApiSurfaceController::class, 'showInventorySession']);
        Route::post('/inventory-sessions/{inventory}/start', [ApiSurfaceController::class, 'startInventorySession'])->middleware('capability:write');
        Route::post('/inventory-sessions/{inventory}/scan', [ApiSurfaceController::class, 'scanInventorySession'])->middleware('capability:write');
        Route::post('/inventory-sessions/{inventory}/review', [ApiSurfaceController::class, 'reviewInventorySession'])->middleware('capability:write');
        Route::post('/inventory-sessions/{inventory}/close', [ApiSurfaceController::class, 'closeInventorySession'])->middleware('capability:write');
        Route::post('/inventory-sessions/{inventory}/cancel', [ApiSurfaceController::class, 'cancelInventorySession'])->middleware('capability:write');
        Route::get('/tires', [TireApiController::class, 'tires']);
        Route::post('/tires', [ApiSurfaceController::class, 'storeTire'])->middleware('capability:write');
        Route::post('/tires/lookup', [ApiSurfaceController::class, 'lookup']);
        Route::get('/tires/{tire}', [TireApiController::class, 'show']);
        Route::post('/tires/{tire}', [TireApiController::class, 'update'])->middleware('capability:write');
        Route::get('/tires/{tire}/history', [TireApiController::class, 'history']);
        Route::get('/tires/{tire}/prediction', [TireApiController::class, 'prediction']);
        Route::get('/tires/{tire}/life-report', [TireApiController::class, 'lifeReport']);
        Route::get('/telemetry', [TireApiController::class, 'telemetry'])->middleware('capability:retire');
        Route::get('/units', [TireApiController::class, 'units']);
        Route::get('/units/{unit}/layout', [TireApiController::class, 'unitLayout']);
        Route::get('/units/{unit}/positions/{position}/candidates', [TireApiController::class, 'positionCandidates']);

        Route::post('/tires/{tire}/recap-wear', [TireApiController::class, 'setRecapWear'])->middleware('capability:write');
        Route::post('/tires/{tire}/condition', [TireApiController::class, 'setCondition'])->middleware('capability:write');
        Route::post('/tires/{tire}/incident', [TireApiController::class, 'incident'])->middleware('capability:write');
        Route::post('/tires/{tire}/measurement', [TireApiController::class, 'measurement'])->middleware('capability:write');
        Route::post('/tires/{tire}/return-stock', [TireApiController::class, 'returnToStock'])->middleware('capability:write');
        Route::post('/units/{unit}/tire-operations', [TireApiController::class, 'operate'])->middleware('capability:write');
        Route::post('/work-orders', [ApiSurfaceController::class, 'storeWorkOrder'])->middleware('capability:write');
        Route::post('/tires/{tire}/retire', [TireApiController::class, 'retire'])->middleware('capability:retire');
    });
});
