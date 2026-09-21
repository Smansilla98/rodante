<?php

namespace Database\Seeders;

use App\Enums\IncidentType;
use App\Enums\LocationKind;
use App\Enums\UnitDuty;
use App\Enums\UnitStatus;
use App\Enums\UserRole;
use App\Enums\WorkOrderType;
use App\Models\Base;
use App\Models\Company;
use App\Models\Fleet;
use App\Models\FleetUnit;
use App\Models\MovementReason;
use App\Models\RetreadShop;
use App\Models\Supplier;
use App\Models\Tire;
use App\Models\TireBrand;
use App\Models\TireModel;
use App\Models\TireSize;
use App\Models\UnitConfiguration;
use App\Models\UnitType;
use App\Models\User;
use App\Services\CouplingService;
use App\Services\IncidentService;
use App\Services\InventoryService;
use App\Services\LocationService;
use App\Services\MeasurementService;
use App\Services\PurchaseService;
use App\Services\RetirementService;
use App\Services\TireOperationService;
use App\Services\WorkOrderService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Escenarios de QA para probar manualmente (web y mobile) todos los estados y flujos
 * principales del dominio: roles, unidades no motrices/inactivas/spare, bitrén, todos
 * los TireStatus alcanzables, órdenes de trabajo en cada estado/tipo, sesiones de
 * inventario en cada estado, y los casos límite de login (empresa desactivada, cuenta
 * desactivada, mismo usuario en dos empresas).
 *
 * A PROPÓSITO no está registrado en DatabaseSeeder: nunca debe correr solo por
 * `php artisan migrate --seed` (eso es lo que dispara el release de Railway en
 * producción, ver scripts/railway-db-setup.sh). Correrlo explícitamente en local/staging:
 *
 *   php artisan db:seed --class=QaScenariosSeeder
 *
 * Requiere haber corrido antes CatalogSeeder + DemoSeeder (usa su base/flota/usuarios).
 */
class QaScenariosSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('QaScenariosSeeder no corre en production — es un seeder de datos de prueba.');

            return;
        }

        $company = Company::demo();
        TenantContext::for($company, function () use ($company) {
            $this->seedDemoCompanyExtras($company);
        });

        $this->seedSecondaryCompany();
        $this->seedInactiveCompany();
    }

    private function seedDemoCompanyExtras(Company $company): void
    {
        $admin = User::query()->where('username', 'admin')->where('company_id', $company->id)->first();
        if (! $admin) {
            $this->command?->warn('No encontré el usuario admin de la empresa demo — corré DemoSeeder primero.');

            return;
        }

        $base = Base::where('code', 'SLT')->where('company_id', $company->id)->first();
        $rosario = Base::where('code', 'ROS')->where('company_id', $company->id)->first();
        $fleet = Fleet::where('code', 'AXN-COMB')->where('company_id', $company->id)->first();
        $alcohol = Fleet::where('code', 'AXN-ALC')->where('company_id', $company->id)->first();

        $this->seedEdgeUsers($company, $rosario, $alcohol);
        $this->seedEdgeUnits($company, $base, $fleet);
        $this->seedBitren($company, $admin, $base, $fleet);
        $this->seedTireLifecycleScenarios($company, $admin, $base);
        $this->seedInventorySessions($admin, $base);
    }

    /** Usuario acotado a una sola base/flota (prueba real de AccessScope) + cuenta desactivada. */
    private function seedEdgeUsers(Company $company, ?Base $rosario, ?Fleet $alcohol): void
    {
        $jefeRosario = User::query()->firstOrCreate(
            ['username' => 'jefe_rosario', 'company_id' => $company->id],
            [
                'name' => 'Rosario Gómez',
                'email' => 'jefe_rosario@flota.test',
                'password' => 'password',
                'role' => UserRole::JefeSector,
                'company_id' => $company->id,
                'is_active' => true,
            ]
        );
        if ($rosario) {
            $jefeRosario->bases()->sync([$rosario->id]);
        }
        if ($alcohol) {
            $jefeRosario->fleets()->sync([$alcohol->id]);
        }

        User::query()->firstOrCreate(
            ['username' => 'operario_inactivo', 'company_id' => $company->id],
            [
                'name' => 'Cuenta Desactivada QA',
                'email' => 'operario_inactivo@flota.test',
                'password' => 'password',
                'role' => UserRole::Operario,
                'company_id' => $company->id,
                'is_active' => false,
            ]
        );
    }

    /** Unidades de borde: batea (no motriz), inactiva, spare — ninguna existe todavía en DemoSeeder. */
    private function seedEdgeUnits(Company $company, ?Base $base, ?Fleet $fleet): void
    {
        if (! $base || ! $fleet) {
            return;
        }

        $bateaType = UnitType::where('code', 'BATEA')->where('company_id', $company->id)->first();
        $tractorType = UnitType::where('code', 'TRACTOR')->where('company_id', $company->id)->first();
        $cfg2s = UnitConfiguration::where('code', '2E-S')->where('company_id', $company->id)->first();
        $cfg64 = UnitConfiguration::where('code', '6X4')->where('company_id', $company->id)->first();

        if ($bateaType && $cfg2s) {
            FleetUnit::firstOrCreate(
                ['plate' => 'QA BATEA1', 'company_id' => $company->id],
                [
                    'fleet_id' => $fleet->id,
                    'base_id' => $base->id,
                    'unit_type_id' => $bateaType->id,
                    'unit_configuration_id' => $cfg2s->id,
                    'brand' => 'Randon',
                    'model_name' => 'Batea vial',
                    'current_odometer' => 0,
                    'status' => UnitStatus::Activa,
                    'duty' => UnitDuty::Regional,
                    'specs' => ['tire_width' => 295],
                ]
            );
        }

        if ($tractorType && $cfg64) {
            FleetUnit::firstOrCreate(
                ['plate' => 'QA INACT1', 'company_id' => $company->id],
                [
                    'fleet_id' => $fleet->id,
                    'base_id' => $base->id,
                    'unit_type_id' => $tractorType->id,
                    'unit_configuration_id' => $cfg64->id,
                    'brand' => 'Scania',
                    'model_name' => 'R420',
                    'current_odometer' => 250000,
                    'status' => UnitStatus::Inactiva,
                    'duty' => UnitDuty::LargaDistancia,
                ]
            );
            FleetUnit::firstOrCreate(
                ['plate' => 'QA SPARE1', 'company_id' => $company->id],
                [
                    'fleet_id' => $fleet->id,
                    'base_id' => $base->id,
                    'unit_type_id' => $tractorType->id,
                    'unit_configuration_id' => $cfg64->id,
                    'brand' => 'Volvo',
                    'model_name' => 'FH 460',
                    'current_odometer' => 12000,
                    'status' => UnitStatus::Spare,
                    'duty' => UnitDuty::Mixto,
                ]
            );
        }
    }

    /** Bitrén: un segundo acoplado sobre un tractor que DemoSeeder ya dejó con uno. */
    private function seedBitren(Company $company, User $admin, ?Base $base, ?Fleet $fleet): void
    {
        $tractor = FleetUnit::where('plate', 'HKH 448')->where('company_id', $company->id)->first();
        $semiType = UnitType::where('code', 'SEMIRREMOLQUE')->where('company_id', $company->id)->first();
        $cfg2s = UnitConfiguration::where('code', '2E-S')->where('company_id', $company->id)->first();
        if (! $tractor || ! $semiType || ! $cfg2s || ! $base || ! $fleet) {
            return;
        }

        $trailer2 = FleetUnit::firstOrCreate(
            ['plate' => 'QA TRAIL2', 'company_id' => $company->id],
            [
                'fleet_id' => $fleet->id,
                'base_id' => $base->id,
                'unit_type_id' => $semiType->id,
                'unit_configuration_id' => $cfg2s->id,
                'brand' => 'Bonano',
                'model_name' => 'Semirremolque lineal',
                'current_odometer' => 0,
                'status' => UnitStatus::Activa,
                'specs' => ['tire_width' => 385],
            ]
        );

        try {
            app(CouplingService::class)->couple(
                $tractor,
                $trailer2,
                (int) $tractor->current_odometer,
                $admin,
                'Bitrén QA — segundo acoplado'
            );
        } catch (\Throwable) {
            // Ya acoplado (idempotencia al re-correr el seeder) o no aplica en este dataset.
        }
    }

    /**
     * Un lote de cubiertas propio de QA (numeración alta para no chocar con la demo)
     * llevado, con los servicios reales, por cada estado alcanzable de TireStatus más
     * todo el ciclo de una orden de trabajo (los 4 estados) y de una de recapado.
     */
    private function seedTireLifecycleScenarios(Company $company, User $admin, ?Base $base): void
    {
        if (! $base || Tire::where('individual_number', 90000)->where('company_id', $company->id)->exists()) {
            return;
        }

        $pirelli = TireBrand::where('name', 'Pirelli')->where('company_id', $company->id)->first();
        $fh01 = TireModel::where('code', 'FH:01')->where('company_id', $company->id)->first();
        $size = TireSize::where('code', '295/80 R22.5')->where('company_id', $company->id)->first();
        $supplier = Supplier::where('company_id', $company->id)->first();
        if (! $pirelli || ! $fh01 || ! $size || ! $supplier) {
            $this->command?->warn('Faltan catálogos base (marca/modelo/medida/proveedor) — corré CatalogSeeder primero.');

            return;
        }

        $purchases = app(PurchaseService::class);
        $purchase = $purchases->create([
            'supplier_id' => $supplier->id,
            'base_id' => $base->id,
            'purchased_at' => now()->subMonths(2)->toDateString(),
            'notes' => 'Lote QA — escenarios completos',
            'items' => [
                ['tire_brand_id' => $pirelli->id, 'tire_model_id' => $fh01->id, 'tire_size_id' => $size->id, 'quantity' => 10, 'first_number' => 90000],
            ],
        ], $admin);
        $purchases->confirm($purchase, $admin);

        $tires = Tire::where('company_id', $company->id)
            ->whereBetween('individual_number', [90000, 90009])
            ->orderBy('individual_number')
            ->get()
            ->values();
        if ($tires->count() < 10) {
            return;
        }

        $locations = app(LocationService::class);
        $measurements = app(MeasurementService::class);
        $incidents = app(IncidentService::class);
        $retirements = app(RetirementService::class);
        $workOrders = app(WorkOrderService::class);
        $shop = RetreadShop::where('company_id', $company->id)->first();
        $reasonBaja = MovementReason::where('company_id', $company->id)->where('applies_to', 'BAJA')->where('code', 'DESGASTE')->first();

        // [0] Reserva
        $locations->place($tires[0], LocationKind::Reserva, $base->id);

        // [1] En reparación (directo, sin pasar por una unidad)
        $locations->place($tires[1], LocationKind::EnReparacion, $base->id);

        // [2] Historial de medición (dos lecturas de desgaste) — alimenta la predicción de vida útil.
        $tires[2]->load('size.zones');
        $zones = $tires[2]->size?->zones;
        if ($zones && $zones->isNotEmpty()) {
            $measurements->record($tires[2], [
                'readings' => $zones->map(fn ($z) => ['zone_id' => $z->id, 'millimeters' => 14.0])->all(),
            ], $admin);
            $measurements->record($tires[2], [
                'readings' => $zones->map(fn ($z) => ['zone_id' => $z->id, 'millimeters' => 9.5])->all(),
            ], $admin);
        }

        // [3] Incidente (pinchadura) sobre una cubierta en stock.
        $incidents->register($tires[3], [
            'type' => IncidentType::Pinchadura->value,
            'description' => 'Pinchadura de prueba QA',
        ], $admin);

        // [4] De baja.
        if ($reasonBaja) {
            $retirements->retire($tires[4], [
                'reason_id' => $reasonBaja->id,
                'notes' => 'Baja QA — fin de vida',
            ], $admin);
        }

        // [5]-[9] Órdenes de trabajo en cada estado + un recapado.
        if ($shop) {
            $workOrders->open($admin, $tires[5], $shop, WorkOrderType::Reparacion, 'OT QA — abierta');

            $enTaller = $workOrders->open($admin, $tires[6], $shop, WorkOrderType::Reparacion, 'OT QA — en taller');
            $workOrders->sendToShop($enTaller, $admin);

            $cerrada = $workOrders->open($admin, $tires[7], $shop, WorkOrderType::Reparacion, 'OT QA — cerrada');
            $workOrders->sendToShop($cerrada, $admin);
            $workOrders->close($cerrada, $admin, 15000, 'Cierre QA');

            $cancelada = $workOrders->open($admin, $tires[8], $shop, WorkOrderType::Reparacion, 'OT QA — cancelada');
            $workOrders->cancel($cancelada, $admin, 'Cancelada QA');

            $workOrders->open($admin, $tires[9], $shop, WorkOrderType::Recapado, 'OT QA — recapado abierto');
        }
    }

    /** Una sesión de inventario en cada InventorySessionStatus. */
    private function seedInventorySessions(User $admin, ?Base $base): void
    {
        if (! $base) {
            return;
        }

        $inv = app(InventoryService::class);

        $inv->open($admin, $base, 'QA — abierta'); // OPEN

        $counting = $inv->open($admin, $base, 'QA — en conteo');
        $inv->startCounting($counting, $admin); // COUNTING

        $review = $inv->open($admin, $base, 'QA — en revisión');
        $inv->startCounting($review, $admin);
        $inv->submitForReview($review, $admin); // REVIEW

        $closed = $inv->open($admin, $base, 'QA — cerrada');
        $inv->startCounting($closed, $admin);
        $inv->submitForReview($closed, $admin);
        $inv->close($closed, $admin); // CLOSED

        $cancelled = $inv->open($admin, $base, 'QA — cancelada');
        $inv->cancel($cancelled, $admin, 'Cancelada QA'); // CANCELLED
    }

    /**
     * Segunda empresa activa con un usuario "jefe" — el mismo username que ya existe en
     * la empresa demo. Al loguearse con "jefe" sin company_id, TokenController debe pedir
     * que se especifique la empresa (ver el flujo ya probado en mobile/app/login.tsx).
     */
    private function seedSecondaryCompany(): void
    {
        $company = Company::firstOrCreate(
            ['slug' => 'qa-secundaria'],
            ['name' => 'QA — Empresa secundaria', 'is_active' => true],
        );
        TenantContext::for($company, function () use ($company) {
            User::query()->firstOrCreate(
                ['username' => 'jefe', 'company_id' => $company->id],
                [
                    'name' => 'Jefe QA Secundaria',
                    'email' => 'jefe.secundaria@flota.test',
                    'password' => 'password',
                    'role' => UserRole::JefeSector,
                    'company_id' => $company->id,
                    'is_active' => true,
                ]
            );
        });
    }

    /** Empresa desactivada — el login debe rechazar con "La empresa está desactivada." */
    private function seedInactiveCompany(): void
    {
        $company = Company::firstOrCreate(
            ['slug' => 'qa-desactivada'],
            ['name' => 'QA — Empresa desactivada', 'is_active' => false],
        );
        TenantContext::for($company, function () use ($company) {
            User::query()->firstOrCreate(
                ['username' => 'admin_desactivada', 'company_id' => $company->id],
                [
                    'name' => 'Admin Empresa Desactivada',
                    'email' => 'admin.desactivada@flota.test',
                    'password' => 'password',
                    'role' => UserRole::Administrador,
                    'company_id' => $company->id,
                    'is_active' => true,
                    'is_super_admin' => true,
                ]
            );
        });
    }
}
