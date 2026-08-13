<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Catalog\BrandController;
use App\Http\Controllers\Catalog\ModelController;
use App\Http\Controllers\Catalog\SimpleCatalogController;
use App\Http\Controllers\Catalog\SizeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Odometer\OdometerController;
use App\Http\Controllers\Purchase\PurchaseController;
use App\Http\Controllers\Report\ReportController;
use App\Http\Controllers\Tire\TireController;
use App\Http\Controllers\Unit\UnitController;
use App\Http\Controllers\User\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/neumaticos', [TireController::class, 'index'])->name('tires.index');
    Route::get('/stock', [TireController::class, 'stock'])->name('tires.stock');
    Route::get('/neumaticos/{tire}', [TireController::class, 'show'])->name('tires.show');
    Route::post('/neumaticos/{tire}/incidencias', [TireController::class, 'storeIncident'])->name('tires.incidents.store');
    Route::post('/neumaticos/{tire}/mediciones', [TireController::class, 'storeMeasurement'])->name('tires.measurements.store');
    Route::post('/neumaticos/{tire}/baja', [TireController::class, 'retire'])->name('tires.retire');

    Route::get('/compras', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::get('/compras/nueva', [PurchaseController::class, 'create'])->name('purchases.create');
    Route::post('/compras', [PurchaseController::class, 'store'])->name('purchases.store');
    Route::get('/compras/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
    Route::post('/compras/{purchase}/confirmar', [PurchaseController::class, 'confirm'])->name('purchases.confirm');

    Route::get('/unidades', [UnitController::class, 'index'])->name('units.index');
    Route::get('/unidades/nueva', [UnitController::class, 'create'])->name('units.create');
    Route::post('/unidades', [UnitController::class, 'store'])->name('units.store');
    Route::get('/unidades/{unit}', [UnitController::class, 'show'])->name('units.show');
    Route::post('/unidades/{unit}/operacion', [UnitController::class, 'operate'])->name('units.operate');
    Route::post('/unidades/{unit}/rotacion', [UnitController::class, 'rotate'])->name('units.rotate');
    Route::post('/unidades/{unit}/acoplar', [UnitController::class, 'couple'])->name('units.couple');
    Route::post('/unidades/{unit}/desacoplar', [UnitController::class, 'uncouple'])->name('units.uncouple');
    Route::post('/unidades/{unit}/configuracion', [UnitController::class, 'changeConfiguration'])->name('units.configuration');

    Route::get('/odometros', [OdometerController::class, 'index'])->name('odometers.index');
    Route::post('/odometros/{reading}/validar', [OdometerController::class, 'validateReading'])->name('odometers.validate');
    Route::post('/odometros/{reading}/rechazar', [OdometerController::class, 'reject'])->name('odometers.reject');

    Route::get('/reportes/kilometros', [ReportController::class, 'kilometers'])->name('reports.kilometers');
    Route::get('/reportes/consumo', [ReportController::class, 'consumption'])->name('reports.consumption');
    Route::get('/reportes/incidencias', [ReportController::class, 'incidents'])->name('reports.incidents');
    Route::get('/auditoria', [ReportController::class, 'audit'])->name('reports.audit');

    Route::middleware('role:ADMINISTRADOR')->group(function () {
        Route::get('/catalogo/marcas', [BrandController::class, 'index'])->name('brands.index');
        Route::post('/catalogo/marcas', [BrandController::class, 'store'])->name('brands.store');
        Route::put('/catalogo/marcas/{brand}', [BrandController::class, 'update'])->name('brands.update');

        Route::get('/catalogo/modelos', [ModelController::class, 'index'])->name('models.index');
        Route::post('/catalogo/modelos', [ModelController::class, 'store'])->name('models.store');
        Route::put('/catalogo/modelos/{model}', [ModelController::class, 'update'])->name('models.update');

        Route::get('/catalogo/medidas', [SizeController::class, 'index'])->name('sizes.index');
        Route::post('/catalogo/medidas', [SizeController::class, 'store'])->name('sizes.store');

        Route::get('/catalogo/flotas', [SimpleCatalogController::class, 'fleets'])->name('fleets.index');
        Route::post('/catalogo/flotas', [SimpleCatalogController::class, 'storeFleet'])->name('fleets.store');
        Route::get('/catalogo/bases', [SimpleCatalogController::class, 'bases'])->name('bases.index');
        Route::post('/catalogo/bases', [SimpleCatalogController::class, 'storeBase'])->name('bases.store');
        Route::get('/catalogo/proveedores', [SimpleCatalogController::class, 'suppliers'])->name('suppliers.index');
        Route::post('/catalogo/proveedores', [SimpleCatalogController::class, 'storeSupplier'])->name('suppliers.store');
        Route::get('/catalogo/tipos', [SimpleCatalogController::class, 'types'])->name('types.index');
        Route::post('/catalogo/tipos', [SimpleCatalogController::class, 'storeType'])->name('types.store');
        Route::post('/catalogo/motivos', [SimpleCatalogController::class, 'storeReason'])->name('reasons.store');

        Route::get('/usuarios', [UserController::class, 'index'])->name('users.index');
        Route::post('/usuarios', [UserController::class, 'store'])->name('users.store');
    });
});
