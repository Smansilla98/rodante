<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Catalog\BrandController;
use App\Http\Controllers\Catalog\ModelController;
use App\Http\Controllers\Catalog\SimpleCatalogController;
use App\Http\Controllers\Catalog\SizeController;
use App\Http\Controllers\CostController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\IntegrityController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Odometer\OdometerController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\Purchase\PurchaseController;
use App\Http\Controllers\QrController;
use App\Http\Controllers\Report\ReportController;
use App\Http\Controllers\RetreadShopController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Tire\TireController;
use App\Http\Controllers\Unit\UnitController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));
Route::get('/qr/{token}', [QrController::class, 'show'])->name('qr.resolve');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/buscar/sugerencias', [SearchController::class, 'suggest'])->name('search.suggest');
    Route::get('/buscar', [SearchController::class, '__invoke'])->name('search');
    Route::get('/campo', [FieldController::class, 'index'])->name('field.index');
    Route::get('/campo/{tire}', [FieldController::class, 'show'])->name('field.show');
    Route::get('/integridad', [IntegrityController::class, 'index'])->name('integrity.index');

    Route::get('/neumaticos', [TireController::class, 'index'])->name('tires.index');
    Route::get('/stock', [TireController::class, 'stock'])->name('tires.stock');
    Route::get('/neumaticos/{tire}/qr.svg', [QrController::class, 'image'])->name('tires.qr');
    Route::get('/neumaticos/{tire}/imprimir', [PrintController::class, 'tire'])->name('tires.print');
    Route::get('/neumaticos/{tire}', [TireController::class, 'show'])->name('tires.show');
    Route::get('/exportar/cubiertas.csv', [ExportController::class, 'tiresCsv'])->name('exports.tires');

    Route::get('/ordenes', [WorkOrderController::class, 'index'])->name('work-orders.index');
    Route::get('/ordenes/{workOrder}', [WorkOrderController::class, 'show'])->whereNumber('workOrder')->name('work-orders.show');
    Route::get('/ordenes/{workOrder}/imprimir', [PrintController::class, 'workOrder'])->whereNumber('workOrder')->name('work-orders.print');
    Route::get('/recapadoras', [RetreadShopController::class, 'index'])->name('shops.index');
    Route::get('/costos', [CostController::class, 'index'])->name('costs.index');
    Route::get('/avisos', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/avisos/{id}/leer', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('/compras', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::get('/unidades', [UnitController::class, 'index'])->name('units.index');
    Route::get('/odometros', [OdometerController::class, 'index'])->name('odometers.index');

    Route::get('/reportes/kilometros', [ReportController::class, 'kilometers'])->name('reports.kilometers');
    Route::get('/reportes/costo-km', [ReportController::class, 'costPerKm'])->name('reports.cost-km');
    Route::get('/reportes/costo-unidad', [ReportController::class, 'costByAttribution'])->name('reports.cost-attribution');
    Route::get('/reportes/inventario', [ReportController::class, 'inventory'])->name('reports.inventory');
    Route::get('/inventarios', [InventoryController::class, 'index'])->name('inventories.index');
    Route::get('/inventarios/{inventory}', [InventoryController::class, 'show'])->whereNumber('inventory')->name('inventories.show');
    Route::get('/reportes/consumo', [ReportController::class, 'consumption'])->name('reports.consumption');
    Route::get('/reportes/incidencias', [ReportController::class, 'incidents'])->name('reports.incidents');
    Route::get('/auditoria', [ReportController::class, 'audit'])->name('reports.audit');

    Route::get('/ayuda', [HelpController::class, 'index'])->name('help.index');
    Route::get('/ayuda/manual', [HelpController::class, 'manual'])->name('help.manual');

    Route::middleware('capability:write')->group(function () {
        Route::post('/neumaticos/{tire}/incidencias', [TireController::class, 'storeIncident'])->name('tires.incidents.store');
        Route::post('/neumaticos/{tire}/mediciones', [TireController::class, 'storeMeasurement'])->name('tires.measurements.store');
        Route::post('/neumaticos/{tire}/stock', [TireController::class, 'returnToStock'])->name('tires.return-stock');

        Route::get('/compras/nueva', [PurchaseController::class, 'create'])->name('purchases.create');
        Route::post('/compras', [PurchaseController::class, 'store'])->name('purchases.store');
        Route::post('/compras/importar', [ExportController::class, 'importPurchases'])->name('purchases.import');
        Route::post('/compras/{purchase}/confirmar', [PurchaseController::class, 'confirm'])->name('purchases.confirm');

        Route::get('/unidades/nueva', [UnitController::class, 'create'])->name('units.create');
        Route::post('/unidades', [UnitController::class, 'store'])->name('units.store');
        Route::get('/unidades/{unit}/stock', [UnitController::class, 'stockSearch'])->name('units.stock');
        Route::post('/unidades/{unit}/operacion', [UnitController::class, 'operate'])->name('units.operate');
        Route::post('/unidades/{unit}/posicion', [UnitController::class, 'slotAction'])->name('units.slot');
        Route::post('/unidades/{unit}/datos', [UnitController::class, 'updateSpecs'])->name('units.specs');

        Route::get('/ordenes/nueva', [WorkOrderController::class, 'create'])->name('work-orders.create');
        Route::post('/ordenes', [WorkOrderController::class, 'store'])->name('work-orders.store');
        Route::post('/ordenes/{workOrder}/enviar', [WorkOrderController::class, 'send'])->name('work-orders.send');
        Route::post('/ordenes/{workOrder}/cerrar', [WorkOrderController::class, 'close'])->name('work-orders.close');
        Route::post('/ordenes/{workOrder}/cancelar', [WorkOrderController::class, 'cancel'])->name('work-orders.cancel');

        Route::get('/inventarios/nueva', [InventoryController::class, 'create'])->name('inventories.create');
        Route::post('/inventarios', [InventoryController::class, 'store'])->name('inventories.store');
        Route::post('/inventarios/{inventory}/iniciar', [InventoryController::class, 'start'])->whereNumber('inventory')->name('inventories.start');
        Route::post('/inventarios/{inventory}/escanear', [InventoryController::class, 'scan'])->whereNumber('inventory')->name('inventories.scan');
        Route::post('/inventarios/{inventory}/revision', [InventoryController::class, 'review'])->whereNumber('inventory')->name('inventories.review');
        Route::post('/inventarios/{inventory}/cerrar', [InventoryController::class, 'close'])->whereNumber('inventory')->name('inventories.close');
        Route::post('/inventarios/{inventory}/cancelar', [InventoryController::class, 'cancel'])->whereNumber('inventory')->name('inventories.cancel');

        Route::post('/recapadoras', [RetreadShopController::class, 'store'])->name('shops.store');
        Route::put('/recapadoras/{shop}', [RetreadShopController::class, 'update'])->name('shops.update');
    });

    Route::get('/compras/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
    Route::get('/unidades/{unit}', [UnitController::class, 'show'])->name('units.show');

    Route::middleware('capability:retire')->group(function () {
        Route::post('/neumaticos/{tire}/baja', [TireController::class, 'retire'])->name('tires.retire');
    });

    Route::middleware('capability:couplings')->group(function () {
        Route::post('/unidades/{unit}/acoplar', [UnitController::class, 'couple'])->name('units.couple');
        Route::post('/unidades/{unit}/desacoplar', [UnitController::class, 'uncouple'])->name('units.uncouple');
    });

    Route::middleware('capability:config')->group(function () {
        Route::post('/unidades/{unit}/configuracion', [UnitController::class, 'changeConfiguration'])->name('units.configuration');
    });

    Route::middleware('capability:odometer')->group(function () {
        Route::put('/odometros/{reading}', [OdometerController::class, 'update'])->name('odometers.update');
    });

    Route::middleware('capability:abm')->group(function () {
        Route::put('/neumaticos/{tire}', [TireController::class, 'update'])->name('tires.update');

        Route::put('/compras/{purchase}', [PurchaseController::class, 'update'])->name('purchases.update');
        Route::delete('/compras/{purchase}', [PurchaseController::class, 'destroy'])->name('purchases.destroy');

        Route::get('/unidades/{unit}/editar', [UnitController::class, 'edit'])->name('units.edit');
        Route::put('/unidades/{unit}', [UnitController::class, 'update'])->name('units.update');
        Route::delete('/unidades/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');

        Route::get('/catalogo/marcas', [BrandController::class, 'index'])->name('brands.index');
        Route::post('/catalogo/marcas', [BrandController::class, 'store'])->name('brands.store');
        Route::put('/catalogo/marcas/{brand}', [BrandController::class, 'update'])->name('brands.update');
        Route::delete('/catalogo/marcas/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');

        Route::get('/catalogo/modelos', [ModelController::class, 'index'])->name('models.index');
        Route::post('/catalogo/modelos', [ModelController::class, 'store'])->name('models.store');
        Route::put('/catalogo/modelos/{model}', [ModelController::class, 'update'])->name('models.update');
        Route::delete('/catalogo/modelos/{model}', [ModelController::class, 'destroy'])->name('models.destroy');

        Route::get('/catalogo/medidas', [SizeController::class, 'index'])->name('sizes.index');
        Route::post('/catalogo/medidas', [SizeController::class, 'store'])->name('sizes.store');
        Route::put('/catalogo/medidas/{size}', [SizeController::class, 'update'])->name('sizes.update');
        Route::delete('/catalogo/medidas/{size}', [SizeController::class, 'destroy'])->name('sizes.destroy');

        Route::get('/catalogo/flotas', [SimpleCatalogController::class, 'fleets'])->name('fleets.index');
        Route::post('/catalogo/flotas', [SimpleCatalogController::class, 'storeFleet'])->name('fleets.store');
        Route::put('/catalogo/flotas/{fleet}', [SimpleCatalogController::class, 'updateFleet'])->name('fleets.update');
        Route::delete('/catalogo/flotas/{fleet}', [SimpleCatalogController::class, 'destroyFleet'])->name('fleets.destroy');

        Route::get('/catalogo/bases', [SimpleCatalogController::class, 'bases'])->name('bases.index');
        Route::post('/catalogo/bases', [SimpleCatalogController::class, 'storeBase'])->name('bases.store');
        Route::put('/catalogo/bases/{base}', [SimpleCatalogController::class, 'updateBase'])->name('bases.update');
        Route::delete('/catalogo/bases/{base}', [SimpleCatalogController::class, 'destroyBase'])->name('bases.destroy');

        Route::get('/catalogo/proveedores', [SimpleCatalogController::class, 'suppliers'])->name('suppliers.index');
        Route::post('/catalogo/proveedores', [SimpleCatalogController::class, 'storeSupplier'])->name('suppliers.store');
        Route::put('/catalogo/proveedores/{supplier}', [SimpleCatalogController::class, 'updateSupplier'])->name('suppliers.update');
        Route::delete('/catalogo/proveedores/{supplier}', [SimpleCatalogController::class, 'destroySupplier'])->name('suppliers.destroy');

        Route::get('/catalogo/tipos', [SimpleCatalogController::class, 'types'])->name('types.index');
        Route::post('/catalogo/tipos', [SimpleCatalogController::class, 'storeType'])->name('types.store');
        Route::put('/catalogo/tipos/{type}', [SimpleCatalogController::class, 'updateType'])->name('types.update');
        Route::delete('/catalogo/tipos/{type}', [SimpleCatalogController::class, 'destroyType'])->name('types.destroy');
        Route::post('/catalogo/motivos', [SimpleCatalogController::class, 'storeReason'])->name('reasons.store');
        Route::put('/catalogo/motivos/{reason}', [SimpleCatalogController::class, 'updateReason'])->name('reasons.update');
        Route::delete('/catalogo/motivos/{reason}', [SimpleCatalogController::class, 'destroyReason'])->name('reasons.destroy');
        Route::put('/catalogo/configuraciones/{configuration}', [SimpleCatalogController::class, 'updateConfiguration'])->name('configurations.update');
        Route::delete('/catalogo/configuraciones/{configuration}', [SimpleCatalogController::class, 'destroyConfiguration'])->name('configurations.destroy');

        Route::get('/usuarios', [UserController::class, 'index'])->name('users.index');
        Route::post('/usuarios', [UserController::class, 'store'])->name('users.store');
        Route::put('/usuarios/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/usuarios/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});
