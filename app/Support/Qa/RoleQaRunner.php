<?php

namespace App\Support\Qa;

use App\Enums\IncidentType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Base;
use App\Models\Fleet;
use App\Models\FleetUnit;
use App\Models\MovementReason;
use App\Models\OdometerReading;
use App\Models\Supplier;
use App\Models\Tire;
use App\Models\TireBrand;
use App\Models\TireModel;
use App\Models\TirePurchase;
use App\Models\TireSize;
use App\Models\UnitConfiguration;
use App\Models\UnitType;
use App\Models\User;
use App\Services\PurchaseService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;

class RoleQaRunner
{
    /** @var list<array{user: string, role: string, action: string, method: string, path: string, status: int, expected: string, ok: bool, detail: string}> */
    private array $steps = [];

    private int $nextNumber;

    private ?FleetUnit $browseUnit = null;

    private ?Tire $browseTire = null;

    public function __construct(
        private object $http,
        private string $logDir,
        private string $tag,
    ) {
        $this->nextNumber = 870000 + random_int(10, 90) * 100;
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array{ok: int, fail: int, steps: list<array<string, mixed>>, files: list<string>}
     */
    public function run(Collection $users): array
    {
        File::ensureDirectoryExists($this->logDir);
        $this->bootstrapBrowse($users->first(fn (User $user) => $user->role === UserRole::Administrador) ?? $users->first());

        $ordered = $users->sortBy(fn (User $user) => match ($user->role) {
            UserRole::Consulta => 1,
            UserRole::Operario => 2,
            UserRole::Logistica => 3,
            UserRole::JefeSector => 4,
            UserRole::Administrador => 5,
        });

        foreach ($ordered as $user) {
            $this->http->actingAs($user);
            $this->runRole($user);
            $this->writeUserLog($user);
        }

        $summary = $this->writeSummary();

        return $summary;
    }

    private function bootstrapBrowse(User $admin): void
    {
        $this->browseUnit = FleetUnit::query()->orderBy('id')->first();
        if (! $this->browseUnit) {
            $this->browseUnit = FleetUnit::create([
                'fleet_id' => Fleet::first()->id,
                'base_id' => Base::first()->id,
                'unit_type_id' => UnitType::where('code', 'TRACTOR')->first()->id,
                'unit_configuration_id' => UnitConfiguration::where('code', '6X4')->first()->id,
                'plate' => $this->tag.'BR',
                'current_odometer' => 100000,
                'status' => 'ACTIVA',
            ]);
        }

        $this->browseTire = Tire::query()->orderBy('id')->first();
        if (! $this->browseTire) {
            $model = TireModel::where('code', 'FH:01')->firstOrFail();
            $size = $model->sizes()->where('code', '295/80 R22.5')->first() ?: $model->sizes()->first();
            $purchase = app(PurchaseService::class)->create([
                'supplier_id' => Supplier::first()->id,
                'base_id' => Base::first()->id,
                'purchased_at' => now()->toDateString(),
                'items' => [[
                    'tire_brand_id' => $model->tire_brand_id,
                    'tire_model_id' => $model->id,
                    'tire_size_id' => $size->id,
                    'quantity' => 1,
                    'first_number' => $this->takeNumbers(1),
                ]],
            ], $admin);
            app(PurchaseService::class)->confirm($purchase, $admin);
            $this->browseTire = Tire::orderByDesc('id')->first();
        }
    }

    private function runRole(User $user): void
    {
        $this->browseCommon($user);
        $this->probeDenied($user);

        match ($user->role) {
            UserRole::Consulta => null,
            UserRole::Operario => $this->operateSheet($user, couple: false, odometer: false, retire: false, config: false),
            UserRole::Logistica => $this->operateSheet($user, couple: true, odometer: true, retire: false, config: false),
            UserRole::JefeSector => $this->operateSheet($user, couple: true, odometer: true, retire: true, config: true),
            UserRole::Administrador => $this->runAdmin($user),
        };
    }

    private function browseCommon(User $user): void
    {
        $pages = [
            'Tablero' => route('dashboard'),
            'Campo' => route('field.index'),
            'Unidades' => route('units.index'),
            'Planilla' => route('units.show', $this->browseUnit),
            'Stock' => route('tires.stock'),
            'Neumáticos' => route('tires.index'),
            'Ficha cubierta' => route('tires.show', $this->browseTire),
            'Compras' => route('purchases.index'),
            'Odómetros' => route('odometers.index'),
            'Mediciones' => route('measurements.index'),
            'Incidencias listado' => route('incidents.index'),
            'Enganches' => route('couplings.index'),
            'Km por cubierta' => route('reports.kilometers'),
            'Consumo' => route('reports.consumption'),
            'Incidencias resumen' => route('reports.incidents'),
            'Predictivo' => route('reports.predictive'),
            'Informe de vida' => route('tires.life-report', $this->browseTire),
            'Movimientos' => route('reports.audit'),
            'Export cubiertas' => route('exports.tires'),
            'Export mediciones' => route('exports.measurements'),
            'Ayuda por rol' => route('help.index'),
            'Manual' => route('help.manual'),
        ];
        foreach ($pages as $label => $url) {
            $this->hit($user, $label, 'GET', $url, [], [200]);
        }

        $catalogOk = $user->role->canManageCatalogs() ? [200] : [403];
        foreach ([
            'Catálogo marcas' => route('brands.index'),
            'Catálogo modelos' => route('models.index'),
            'Catálogo medidas' => route('sizes.index'),
            'Catálogo flotas' => route('fleets.index'),
            'Catálogo bases' => route('bases.index'),
            'Catálogo proveedores' => route('suppliers.index'),
            'Catálogo tipos' => route('types.index'),
            'Usuarios' => route('users.index'),
        ] as $label => $url) {
            $this->hit($user, $label, 'GET', $url, [], $catalogOk);
        }

        $telemetryOk = $user->role->canViewTelemetry() ? [200] : [403];
        $this->hit($user, 'Telemetría', 'GET', route('reports.telemetry'), [], $telemetryOk);
    }

    private function probeDenied(User $user): void
    {
        $write = $user->role->canWrite() ? [200, 302] : [403];
        $this->hit($user, 'Formulario nueva compra', 'GET', route('purchases.create'), [], $user->role->canWrite() ? [200] : [403]);
        $this->hit($user, 'Formulario nueva unidad', 'GET', route('units.create'), [], $user->role->canWrite() ? [200] : [403]);
        $this->hit($user, 'Editar unidad (ABM)', 'GET', route('units.edit', $this->browseUnit), [], $user->role->canManageAbm() ? [200] : [403]);

        if ($user->role->canWrite()) {
            return;
        }

        $this->hit($user, 'Alta compra (debe bloquear)', 'POST', route('purchases.store'), [
            'supplier_id' => Supplier::first()->id,
            'base_id' => Base::first()->id,
            'purchased_at' => now()->toDateString(),
            'items' => [['tire_brand_id' => TireModel::first()->tire_brand_id, 'quantity' => 1, 'first_number' => 1]],
        ], [403]);

        $this->hit($user, 'Instalar en planilla (debe bloquear)', 'POST', route('units.slot', $this->browseUnit), [
            'action' => 'install',
            'odometer' => 100000,
            'position_id' => $this->steerPositions($this->browseUnit)[0]->id ?? 1,
            'tire_id' => $this->browseTire->id,
        ], [403]);

        $this->hit($user, 'Acoplar (debe bloquear)', 'POST', route('units.couple', $this->browseUnit), [
            'other_unit_id' => $this->browseUnit->id,
            'odometer' => 100000,
        ], [403]);

        $this->hit($user, 'Baja cubierta (debe bloquear)', 'POST', route('tires.retire', $this->browseTire), [
            'reason_id' => MovementReason::where('applies_to', 'BAJA')->value('id'),
        ], [403]);

        $this->hit($user, 'Alta marca (debe bloquear)', 'POST', route('brands.store'), ['name' => $this->tag.'X'], [403]);
    }

    private function operateSheet(User $user, bool $couple, bool $odometer, bool $retire, bool $config): void
    {
        $roleKey = match ($user->role) {
            UserRole::Operario => 'OP',
            UserRole::Logistica => 'LG',
            UserRole::JefeSector => 'JE',
            UserRole::Administrador => 'AD',
            default => 'XX',
        };
        $prefix = strtoupper(substr($this->tag, 0, 6).$roleKey);
        $tractorPlate = strtoupper($prefix.'T');
        $trailerPlate = strtoupper($prefix.'R');

        $fleet = Fleet::first();
        $base = Base::first();
        $tractorType = UnitType::where('code', 'TRACTOR')->first();
        $tankType = UnitType::where('code', 'TANQUE')->first();
        $cfg64 = UnitConfiguration::where('code', '6X4')->first();
        $cfg3s = UnitConfiguration::where('code', '3E-S')->first();

        $this->hit($user, 'Alta tractor', 'POST', route('units.store'), [
            'fleet_id' => $fleet->id,
            'base_id' => $base->id,
            'unit_type_id' => $tractorType->id,
            'unit_configuration_id' => $cfg64->id,
            'plate' => $tractorPlate,
            'brand' => 'QA',
            'model_name' => $user->username,
            'current_odometer' => 200000,
        ], [302], true);

        $this->hit($user, 'Alta tanque lineal 385', 'POST', route('units.store'), [
            'fleet_id' => $fleet->id,
            'base_id' => $base->id,
            'unit_type_id' => $tankType->id,
            'unit_configuration_id' => $cfg3s->id,
            'plate' => $trailerPlate,
            'brand' => 'QA',
            'model_name' => 'Tanque',
            'specs' => ['tire_width' => 385, 'product' => 'combustible'],
        ], [302], true);

        $tractor = FleetUnit::where('plate', $tractorPlate)->first();
        $trailer = FleetUnit::where('plate', $trailerPlate)->first();
        if (! $tractor || ! $trailer) {
            $this->record($user, 'Resolver unidades creadas', 'GET', '/', 500, '200', false, 'No se encontraron las patentes '.$tractorPlate.'/'.$trailerPlate);

            return;
        }

        $model = TireModel::where('code', 'FH:01')->firstOrFail();
        $size = $model->sizes()->where('code', '295/80 R22.5')->firstOrFail();
        $first = $this->takeNumbers(3);

        $this->hit($user, 'Alta compra borrador', 'POST', route('purchases.store'), [
            'supplier_id' => Supplier::first()->id,
            'base_id' => $base->id,
            'purchased_at' => now()->toDateString(),
            'notes' => 'QA '.$user->username,
            'items' => [[
                'tire_brand_id' => $model->tire_brand_id,
                'tire_model_id' => $model->id,
                'tire_size_id' => $size->id,
                'quantity' => 3,
                'first_number' => $first,
            ]],
        ], [302], true);

        $purchase = TirePurchase::query()->where('user_id', $user->id)->latest('id')->first();
        if ($purchase) {
            $this->hit($user, 'Ver compra', 'GET', route('purchases.show', $purchase), [], [200]);
            $this->hit($user, 'Confirmar compra → stock', 'POST', route('purchases.confirm', $purchase), [], [302], true);
        }

        $tires = Tire::where('individual_number', '>=', $first)->orderBy('individual_number')->limit(3)->get();
        $steer = $this->steerPositions($tractor);
        if ($tires->count() < 2 || $steer->count() < 2) {
            $this->record($user, 'Cubiertas/ubicaciones', 'GET', '/', 500, '200', false, 'Faltan cubiertas o ubicaciones de dirección');

            return;
        }

        $a = $tires[0];
        $b = $tires[1];
        $pos1 = $steer[0];
        $pos2 = $steer[1];

        $this->hit($user, 'Instalar cubierta en dirección', 'POST', route('units.slot', $tractor), [
            'action' => 'install',
            'odometer' => 200100,
            'position_id' => $pos1->id,
            'tire_id' => $a->id,
        ], [302], true);

        $zones = $a->fresh()->size->zones;
        $readings = [];
        foreach ($zones as $i => $zone) {
            $readings[$i] = ['zone_id' => $zone->id, 'millimeters' => $i < 2 ? 12 : 11];
        }
        $this->hit($user, 'Medición de profundidad', 'POST', route('units.slot', $tractor), [
            'action' => 'medicion',
            'odometer' => 200100,
            'position_id' => $pos1->id,
            'expected_tire_id' => $a->id,
            'readings' => $readings,
        ], [302], true);

        $this->hit($user, 'Incidencia inspección', 'POST', route('units.slot', $tractor), [
            'action' => 'incidencia',
            'odometer' => 200100,
            'position_id' => $pos1->id,
            'expected_tire_id' => $a->id,
            'incident_type' => IncidentType::Inspeccion->value,
            'description' => 'QA inspección',
        ], [302], true);

        $this->hit($user, 'Rotación a la otra dirección', 'POST', route('units.slot', $tractor), [
            'action' => 'rotacion',
            'odometer' => 200150,
            'position_id' => $pos1->id,
            'expected_tire_id' => $a->id,
            'to_position_id' => $pos2->id,
        ], [302], true);

        $this->hit($user, 'Cambio de cubierta', 'POST', route('units.slot', $tractor), [
            'action' => 'cambio',
            'odometer' => 200200,
            'position_id' => $pos2->id,
            'expected_tire_id' => $a->id,
            'tire_id' => $b->id,
            'notes' => 'QA cambio',
        ], [302], true);

        $this->hit($user, 'Pinchadura → reparación', 'POST', route('units.slot', $tractor), [
            'action' => 'pinchadura',
            'odometer' => 200250,
            'position_id' => $pos2->id,
            'expected_tire_id' => $b->id,
            'notes' => 'QA pinchadura',
        ], [302], true);

        $c = $tires[2] ?? $a->fresh();
        if ($c->fresh()->status->value === 'STOCK') {
            $this->hit($user, 'Reinstalar para retiro', 'POST', route('units.slot', $tractor), [
                'action' => 'install',
                'odometer' => 200300,
                'position_id' => $pos1->id,
                'tire_id' => $c->id,
            ], [302], true);

            $this->hit($user, 'Retirar a stock', 'POST', route('units.slot', $tractor), [
                'action' => 'retirar',
                'odometer' => 200350,
                'position_id' => $pos1->id,
                'expected_tire_id' => $c->id,
                'reason_id' => MovementReason::where('code', 'ROTACION')->value('id'),
                'destination' => 'STOCK',
            ], [302], true);
        }

        $this->hit($user, 'Incidencia en ficha', 'POST', route('tires.incidents.store', $a->fresh()), [
            'type' => IncidentType::Otra->value,
            'description' => 'QA ficha',
        ], [302], true);

        if ($couple) {
            $this->hit($user, 'Acoplar tanque', 'POST', route('units.couple', $tractor), [
                'other_unit_id' => $trailer->id,
                'odometer' => 200400,
                'notes' => 'QA acople',
            ], [302], true);
            $this->hit($user, 'Desacoplar', 'POST', route('units.uncouple', $tractor), [
                'odometer' => 200450,
            ], [302], true);
        } else {
            $this->hit($user, 'Acoplar (debe bloquear)', 'POST', route('units.couple', $tractor), [
                'other_unit_id' => $trailer->id,
                'odometer' => 200400,
            ], [403]);
        }

        $reading = OdometerReading::where('unit_id', $tractor->id)->latest('id')->first();
        if ($reading) {
            if ($odometer) {
                $this->hit($user, 'Corregir odómetro', 'PUT', route('odometers.update', $reading), [
                    'value' => (int) $reading->value,
                    'notes' => 'QA corrección mismo km',
                ], [302], true);
            } else {
                $this->hit($user, 'Corregir odómetro (debe bloquear)', 'PUT', route('odometers.update', $reading), [
                    'value' => (int) $reading->value + 1,
                ], [403]);
            }
        }

        $stockTire = Tire::whereIn('id', $tires->pluck('id'))->where('status', 'STOCK')->first() ?: $a->fresh();
        if ($retire) {
            if ($stockTire->status->value !== 'STOCK' && $stockTire->status->value !== 'EN_REPARACION') {
                $stockTire = Tire::where('status', 'STOCK')->latest('id')->first() ?: $stockTire;
            }
            $this->hit($user, 'Recapado (vida nueva)', 'POST', route('tires.incidents.store', $stockTire), [
                'type' => IncidentType::Recapado->value,
                'description' => 'QA recap',
            ], [302], true);
            $this->hit($user, 'Baja definitiva', 'POST', route('tires.retire', $stockTire->fresh()), [
                'reason_id' => MovementReason::where('code', 'FIN_DE_VIDA')->value('id'),
                'notes' => 'QA baja',
            ], [302], true);

            $cfg62 = UnitConfiguration::where('code', '6X2')->first();
            if ($config && $cfg62) {
                $this->hit($user, 'Cambio de configuración 6X4→6X2', 'POST', route('units.configuration', $tractor), [
                    'unit_configuration_id' => $cfg62->id,
                    'reason' => 'QA urbana a ripio',
                    'odometer' => $tractor->current_odometer,
                ], [302], true);
            }
        } else {
            $this->hit($user, 'Baja (debe bloquear)', 'POST', route('tires.retire', $stockTire), [
                'reason_id' => MovementReason::where('applies_to', 'BAJA')->value('id'),
            ], [403]);
            $this->hit($user, 'Cambio de configuración (debe bloquear)', 'POST', route('units.configuration', $tractor), [
                'unit_configuration_id' => UnitConfiguration::where('code', '6X2')->value('id'),
                'reason' => 'QA',
            ], [403]);
        }

        $this->hit($user, 'Alta marca (debe bloquear no-admin)', 'POST', route('brands.store'), [
            'name' => $this->tag.$user->username,
        ], $user->role->canManageCatalogs() ? [302] : [403]);
    }

    private function runAdmin(User $user): void
    {
        $this->operateSheet($user, couple: true, odometer: true, retire: true, config: true);

        $brandName = 'QA Marca '.$this->tag;
        $this->hit($user, 'Alta marca', 'POST', route('brands.store'), ['name' => $brandName], [302], true);
        $brand = TireBrand::where('name', $brandName)->first();
        if ($brand) {
            $this->hit($user, 'Modificar marca', 'PUT', route('brands.update', $brand), [
                'name' => $brandName.' edit',
                'is_active' => '1',
            ], [302], true);
            $this->hit($user, 'Baja marca sin uso', 'DELETE', route('brands.destroy', $brand), [], [302], true);
        }

        $sizeCode = '999/80 R22.5';
        $this->hit($user, 'Alta medida', 'POST', route('sizes.store'), [
            'code' => $sizeCode,
            'uneven_wear_threshold_mm' => 3,
        ], [302], true);
        $size = TireSize::where('code', $sizeCode)->first();
        if ($size) {
            $this->hit($user, 'Modificar medida', 'PUT', route('sizes.update', $size), [
                'code' => $sizeCode,
                'alias' => 'QA',
                'is_active' => '1',
            ], [302], true);
            $this->hit($user, 'Baja medida sin uso', 'DELETE', route('sizes.destroy', $size), [], [302], true);
        }

        $this->hit($user, 'Alta flota', 'POST', route('fleets.store'), [
            'name' => 'QA Flota '.$this->tag,
            'code' => 'QA'.$this->tag,
            'base_ids' => [Base::first()->id],
        ], [302], true);
        $fleet = Fleet::where('code', 'QA'.$this->tag)->first();
        if ($fleet) {
            $this->hit($user, 'Modificar flota', 'PUT', route('fleets.update', $fleet), [
                'name' => 'QA Flota edit',
                'code' => 'QA'.$this->tag,
                'is_active' => '1',
                'base_ids' => [Base::first()->id],
            ], [302], true);
            $this->hit($user, 'Baja flota sin unidades', 'DELETE', route('fleets.destroy', $fleet), [], [302], true);
        }

        $this->hit($user, 'Alta base', 'POST', route('bases.store'), [
            'name' => 'QA Base '.$this->tag,
            'code' => 'QB'.$this->tag,
            'location' => 'Playón QA',
        ], [302], true);
        $qaBase = Base::where('code', 'QB'.$this->tag)->first();
        if ($qaBase) {
            $this->hit($user, 'Modificar base', 'PUT', route('bases.update', $qaBase), [
                'name' => 'QA Base edit',
                'code' => 'QB'.$this->tag,
                'location' => 'Playón',
                'is_active' => '1',
            ], [302], true);
            $this->hit($user, 'Baja base sin unidades', 'DELETE', route('bases.destroy', $qaBase), [], [302], true);
        }

        $this->hit($user, 'Alta proveedor', 'POST', route('suppliers.store'), [
            'name' => 'QA Proveedor '.$this->tag,
            'tax_id' => '20999999991',
        ], [302], true);
        $supplier = Supplier::where('name', 'QA Proveedor '.$this->tag)->first();
        if ($supplier) {
            $this->hit($user, 'Modificar proveedor', 'PUT', route('suppliers.update', $supplier), [
                'name' => 'QA Proveedor '.$this->tag,
                'phone' => '111',
                'is_active' => '1',
            ], [302], true);
            $this->hit($user, 'Baja proveedor sin compras', 'DELETE', route('suppliers.destroy', $supplier), [], [302], true);
        }

        $this->hit($user, 'Alta motivo', 'POST', route('reasons.store'), [
            'code' => 'QA'.$this->tag,
            'name' => 'Motivo QA',
            'applies_to' => 'OTRO',
        ], [302], true);
        $reason = MovementReason::where('code', 'QA'.$this->tag)->first();
        if ($reason) {
            $this->hit($user, 'Modificar motivo', 'PUT', route('reasons.update', $reason), [
                'code' => 'QA'.$this->tag,
                'name' => 'Motivo QA edit',
                'applies_to' => 'OTRO',
                'is_active' => '1',
            ], [302], true);
            $this->hit($user, 'Baja motivo sin uso', 'DELETE', route('reasons.destroy', $reason), [], [302], true);
        }

        $this->hit($user, 'Alta usuario', 'POST', route('users.store'), [
            'name' => 'QA Temp',
            'username' => 'qatemp'.$this->tag,
            'password' => 'password',
            'role' => UserRole::Consulta->value,
            'fleet_ids' => [Fleet::first()->id],
            'base_ids' => [Base::first()->id],
        ], [302], true);
        $temp = User::where('username', 'qatemp'.$this->tag)->first();
        if ($temp) {
            $this->hit($user, 'Modificar usuario', 'PUT', route('users.update', $temp), [
                'name' => 'QA Temp edit',
                'username' => 'qatemp'.$this->tag,
                'role' => UserRole::Consulta->value,
                'is_active' => '1',
                'fleet_ids' => [Fleet::first()->id],
                'base_ids' => [Base::first()->id],
            ], [302], true);
            $this->hit($user, 'Baja usuario sin historial', 'DELETE', route('users.destroy', $temp), [], [302], true);
        }

        $unit = FleetUnit::where('plate', strtoupper(substr($this->tag, 0, 6).'AD').'T')->first()
            ?: $this->browseUnit;
        $this->hit($user, 'Modificar datos de unidad', 'PUT', route('units.update', $unit), [
            'fleet_id' => $unit->fleet_id,
            'base_id' => $unit->base_id,
            'plate' => $unit->plate,
            'brand' => 'QA-EDIT',
            'status' => 'ACTIVA',
        ], [302], true);

        $draftModel = TireModel::where('code', 'FH:01')->first();
        $draftSize = $draftModel->sizes()->where('code', '295/80 R22.5')->first();
        $this->hit($user, 'Borrador para anular', 'POST', route('purchases.store'), [
            'supplier_id' => Supplier::first()->id,
            'base_id' => Base::first()->id,
            'purchased_at' => now()->toDateString(),
            'notes' => 'anular',
            'items' => [[
                'tire_brand_id' => $draftModel->tire_brand_id,
                'tire_model_id' => $draftModel->id,
                'tire_size_id' => $draftSize->id,
                'quantity' => 1,
                'first_number' => $this->takeNumbers(1),
            ]],
        ], [302], true);
        $draft = TirePurchase::where('user_id', $user->id)->where('status', TirePurchase::STATUS_DRAFT)->latest('id')->first();
        if ($draft) {
            $this->hit($user, 'Modificar borrador', 'PUT', route('purchases.update', $draft), [
                'supplier_id' => $draft->supplier_id,
                'base_id' => $draft->base_id,
                'purchased_at' => $draft->purchased_at->toDateString(),
                'notes' => 'anular edit',
            ], [302], true);
            $this->hit($user, 'Anular borrador', 'DELETE', route('purchases.destroy', $draft), [], [302], true);
        }

        $tire = Tire::where('status', 'STOCK')->latest('id')->first() ?: $this->browseTire;
        $this->hit($user, 'Modificar ficha de cubierta', 'PUT', route('tires.update', $tire), [
            'individual_number' => $tire->individual_number,
            'tire_brand_id' => $tire->tire_brand_id,
            'tire_model_id' => $tire->tire_model_id,
            'tire_size_id' => $tire->tire_size_id,
            'condition' => $tire->condition->value,
        ], [302], true);
    }

    /**
     * @param  list<int>  $expected
     */
    private function hit(User $user, string $action, string $method, string $url, array $data, array $expected, bool $needFlash = false): void
    {
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        try {
            if (strtoupper($method) !== 'GET') {
                $data['_token'] = csrf_token();
            }
            $raw = match (strtoupper($method)) {
                'GET' => $this->http->get($url),
                'POST' => $this->http->post($url, $data),
                'PUT' => $this->http->put($url, $data),
                'DELETE' => $this->http->delete($url, $data),
                default => throw new \InvalidArgumentException($method),
            };
            $response = $raw instanceof TestResponse
                ? $raw
                : TestResponse::fromBaseResponse($raw);
            $status = $response->status();
            $flash = null;
            $error = null;
            try {
                $flash = $response->session()->get('success');
                $errors = $response->session()->get('errors');
                $error = $errors ? collect($errors->getBag('default')->all())->flatten()->first() : null;
            } catch (\Throwable) {
                // 403/500 a veces no arrastran sesión.
            }
            $ok = in_array($status, $expected, true);
            if ($ok && $needFlash && $status === 302) {
                $ok = blank($error);
            }
            $detail = $flash ?: ($error ?: ($response->headers->get('Location') ?: ''));
            if ($status >= 500) {
                $exception = $response->exception ?? null;
                $detail = $exception?->getMessage() ?: substr(trim(strip_tags($response->getContent())), 0, 180);
            }
            if (is_string($detail) && strlen($detail) > 180) {
                $detail = substr($detail, 0, 180).'…';
            }
            $this->record($user, $action, $method, $path, $status, implode('|', $expected), $ok, (string) $detail);
        } catch (\Throwable $e) {
            $this->record($user, $action, $method, $path, 500, implode('|', $expected), false, $e->getMessage());
        }
    }

    private function record(User $user, string $action, string $method, string $path, int $status, string $expected, bool $ok, string $detail): void
    {
        $this->steps[] = [
            'user' => $user->username,
            'role' => $user->role->label(),
            'action' => $action,
            'method' => $method,
            'path' => $path,
            'status' => $status,
            'expected' => $expected,
            'ok' => $ok,
            'detail' => $detail,
        ];
    }

    private function writeUserLog(User $user): void
    {
        $mine = array_values(array_filter($this->steps, fn (array $step) => $step['user'] === $user->username));
        $ok = count(array_filter($mine, fn (array $step) => $step['ok']));
        $fail = count($mine) - $ok;
        $audits = AuditLog::where('user_id', $user->id)
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (AuditLog $log) => $log->created_at?->format('H:i:s').' · '.$log->actionLabel().' · '.$log->detail())
            ->all();

        $lines = [
            'QA rol '.$user->role->label().' ('.$user->username.')',
            'Lote '.$this->tag.' · '.now()->toDateTimeString(),
            "Pasos OK: {$ok} · Fallidos: {$fail}",
            str_repeat('-', 72),
        ];
        foreach ($mine as $step) {
            $mark = $step['ok'] ? 'OK' : 'FAIL';
            $lines[] = sprintf(
                '[%s] %s %s %s → %d (esperado %s) %s',
                $mark,
                $step['method'],
                $step['action'],
                $step['path'],
                $step['status'],
                $step['expected'],
                $step['detail'],
            );
        }
        $lines[] = str_repeat('-', 72);
        $lines[] = 'Movimientos registrados por este usuario:';
        $lines = array_merge($lines, $audits !== [] ? $audits : ['(sin auditoría en esta corrida)']);
        File::put($this->logDir.'/'.$user->username.'.log', implode("\n", $lines)."\n");
    }

    /**
     * @return array{ok: int, fail: int, steps: list<array<string, mixed>>, files: list<string>}
     */
    private function writeSummary(): array
    {
        $ok = count(array_filter($this->steps, fn (array $step) => $step['ok']));
        $fail = count($this->steps) - $ok;
        $payload = [
            'tag' => $this->tag,
            'at' => now()->toIso8601String(),
            'ok' => $ok,
            'fail' => $fail,
            'steps' => $this->steps,
        ];
        File::put($this->logDir.'/resumen.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $lines = ['QA completo '.$this->tag, "OK {$ok} · FAIL {$fail}", ''];
        foreach ($this->steps as $step) {
            if (! $step['ok']) {
                $lines[] = sprintf('FAIL %s · %s · %s %s → %d %s', $step['user'], $step['action'], $step['method'], $step['path'], $step['status'], $step['detail']);
            }
        }
        File::put($this->logDir.'/resumen.log', implode("\n", $lines)."\n");

        return [
            'ok' => $ok,
            'fail' => $fail,
            'steps' => $this->steps,
            'dir' => $this->logDir,
        ];
    }

    private function steerPositions(FleetUnit $unit): Collection
    {
        $unit->loadMissing('configuration.positions');

        return $unit->configuration->positions()
            ->where('axle_number', 1)
            ->where('is_spare', false)
            ->orderBy('sort_order')
            ->get();
    }

    private function takeNumbers(int $qty): int
    {
        $first = $this->nextNumber;
        $this->nextNumber += $qty + 3;

        return $first;
    }
}
