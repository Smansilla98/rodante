<?php

namespace Database\Seeders;

use App\Enums\UnitStatus;
use App\Enums\UserRole;
use App\Models\Base;
use App\Models\Fleet;
use App\Models\FleetUnit;
use App\Models\Supplier;
use App\Models\TireBrand;
use App\Models\TireModel;
use App\Models\TireSize;
use App\Models\UnitConfiguration;
use App\Models\UnitType;
use App\Models\Tire;
use App\Models\User;
use App\Services\CouplingService;
use App\Services\PurchaseService;
use App\Services\TireOperationService;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $fleets = Fleet::all();
        $bases = Base::all();

        $users = [
            ['admin', 'Administrador', UserRole::Administrador],
            ['jefe', 'Laura Pérez', UserRole::JefeSector],
            ['logistica', 'Marcos Díaz', UserRole::Logistica],
            ['operario', 'Hugo Benítez', UserRole::Operario],
            ['consulta', 'Ana Ruiz', UserRole::Consulta],
        ];

        foreach ($users as [$username, $name, $role]) {
            $user = User::query()->firstOrCreate(
                ['username' => $username],
                [
                    'name' => $name,
                    'email' => $username.'@flota.test',
                    'password' => 'password',
                    'role' => $role,
                    'is_active' => true,
                ]
            );
            $user->fleets()->sync($fleets->pluck('id'));
            $user->bases()->sync($bases->pluck('id'));
        }

        if (FleetUnit::query()->where('plate', 'HKH 448')->exists()) {
            return;
        }

        $tractorType = UnitType::where('code', 'CAMION_TRACTOR')->first();
        $tankType = UnitType::where('code', 'TANQUE')->first();
        $semiType = UnitType::where('code', 'SEMIRREMOLQUE')->first();
        $cfgT = UnitConfiguration::where('code', '6X24-T')->first();
        $cfgA = UnitConfiguration::where('code', '6X24-A')->first();
        $cfg61 = UnitConfiguration::where('code', '6X1')->first();
        $comb = Fleet::where('code', 'AXN-COMB')->first();
        $alc = Fleet::where('code', 'AXN-ALC')->first();
        $base = Base::where('code', 'SLT')->first();

        $pairs = [
            ['HKH 448', 'FWI 093', 'Scania', 'R450', 184200, $comb, $tankType],
            ['AC 363 CB', 'JNH 143', 'Mercedes-Benz', 'Axor 1933', 210450, $comb, $tankType],
            ['OZK 888', 'FVX 336', 'Volvo', 'FH 460', 156000, $comb, $semiType],
            ['KUW 620', 'LRA 259', 'Iveco', 'Cursor', 98000, $comb, $semiType],
            ['NLO 982', 'DRO 762', 'Volkswagen', '24.280', 72000, $alc, $tankType],
        ];

        foreach ($pairs as [$tractorPlate, $trailerPlate, $brand, $model, $odo, $fleet, $trailerType]) {
            FleetUnit::create([
                'fleet_id' => $fleet->id,
                'base_id' => $base->id,
                'unit_type_id' => $tractorType->id,
                'unit_configuration_id' => $cfgT->id,
                'plate' => $tractorPlate,
                'brand' => $brand,
                'model_name' => $model,
                'current_odometer' => $odo,
                'status' => UnitStatus::Activa,
            ]);
            FleetUnit::create([
                'fleet_id' => $fleet->id,
                'base_id' => $base->id,
                'unit_type_id' => $trailerType->id,
                'unit_configuration_id' => $trailerType->code === 'TANQUE' && $tractorPlate === 'NLO 982' ? $cfg61->id : $cfgA->id,
                'plate' => $trailerPlate,
                'brand' => 'Bonano',
                'model_name' => $trailerType->name,
                'current_odometer' => 0,
                'status' => UnitStatus::Activa,
            ]);
        }

        $admin = User::where('username', 'admin')->first();
        $pirelli = TireBrand::where('name', 'Pirelli')->first();
        $fh01 = TireModel::where('code', 'FH:01')->first();
        $tr01 = TireModel::where('code', 'TR:01')->first();
        $size = TireSize::where('code', '295/80 R22.5')->first();
        $gomon = TireSize::where('code', '385/90 R22.5')->first();
        $supplier = Supplier::where('name', 'Pirelli Neumáticos')->first();

        $purchases = app(PurchaseService::class);
        $purchase = $purchases->create([
            'supplier_id' => $supplier->id,
            'base_id' => $base->id,
            'purchased_at' => '2026-01-10',
            'notes' => 'Lote inicial de cubiertas de plaza',
            'items' => [
                ['tire_brand_id' => $pirelli->id, 'tire_model_id' => $fh01->id, 'tire_size_id' => $size->id, 'quantity' => 12, 'first_number' => 30360],
                ['tire_brand_id' => $pirelli->id, 'tire_model_id' => $tr01->id, 'tire_size_id' => $size->id, 'quantity' => 16, 'first_number' => 30400],
                ['tire_brand_id' => $pirelli->id, 'tire_model_id' => $fh01->id, 'tire_size_id' => $gomon->id, 'quantity' => 4, 'first_number' => 31001],
            ],
        ], $admin);
        $purchases->confirm($purchase, $admin);

        $tractor = FleetUnit::where('plate', 'HKH 448')->first();
        $trailer = FleetUnit::where('plate', 'FWI 093')->first();
        $couplings = app(CouplingService::class);
        $couplings->couple($tractor, $trailer, 184200, $admin, 'Acoplamiento habitual combustible');

        $operations = app(TireOperationService::class);
        $steer = $cfgT->positions()->where('axle_number', 1)->where('is_spare', false)->orderBy('sort_order')->get();
        $drive = $cfgT->positions()->where('axle_number', 2)->where('is_spare', false)->orderBy('sort_order')->get();
        $drive3 = $cfgT->positions()->where('axle_number', 3)->where('is_spare', false)->orderBy('sort_order')->get();

        $byNumber = fn (int $n) => Tire::where('individual_number', $n)->value('id');
        $operations->execute($tractor, [
            'odometer' => 184200,
            'installations' => [
                ['tire_id' => $byNumber(30360), 'position_id' => $steer[0]->id],
                ['tire_id' => $byNumber(30361), 'position_id' => $steer[1]->id],
                ['tire_id' => $byNumber(30400), 'position_id' => $drive[0]->id],
                ['tire_id' => $byNumber(30401), 'position_id' => $drive[1]->id],
                ['tire_id' => $byNumber(30402), 'position_id' => $drive[2]->id],
                ['tire_id' => $byNumber(30403), 'position_id' => $drive[3]->id],
                ['tire_id' => $byNumber(30404), 'position_id' => $drive3[0]->id],
                ['tire_id' => $byNumber(30405), 'position_id' => $drive3[1]->id],
                ['tire_id' => $byNumber(30406), 'position_id' => $drive3[2]->id],
                ['tire_id' => $byNumber(30407), 'position_id' => $drive3[3]->id],
            ],
        ], $admin);

        $trailerSlots = $cfgA->positions()->where('is_spare', false)->orderBy('sort_order')->get();
        $operations->execute($trailer, [
            'odometer' => 184200,
            'installations' => [
                ['tire_id' => $byNumber(30362), 'position_id' => $trailerSlots[3]->id],
                ['tire_id' => $byNumber(30363), 'position_id' => $trailerSlots[4]->id],
                ['tire_id' => $byNumber(30364), 'position_id' => $trailerSlots[7]->id],
                ['tire_id' => $byNumber(30365), 'position_id' => $trailerSlots[8]->id],
                ['tire_id' => $byNumber(30366), 'position_id' => $trailerSlots[11]->id],
            ],
        ], $admin);
    }
}
