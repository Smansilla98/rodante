<?php

namespace Tests\Concerns;

use App\Enums\UserRole;
use App\Models\Base;
use App\Models\Fleet;
use App\Models\FleetUnit;
use App\Models\Supplier;
use App\Models\Tire;
use App\Models\TireModel;
use App\Models\TireSize;
use App\Models\UnitConfiguration;
use App\Models\UnitType;
use App\Models\User;
use App\Services\PurchaseService;
use Database\Seeders\CatalogSeeder;

trait CreatesDomain
{
    protected User $admin;

    protected function seedDomain(): void
    {
        $this->seed(CatalogSeeder::class);
        $this->admin = User::factory()->create([
            'username' => 'admin-test',
            'role' => UserRole::Administrador,
        ]);
        $this->admin->fleets()->sync(Fleet::pluck('id'));
        $this->admin->bases()->sync(Base::pluck('id'));
        $this->actingAs($this->admin);
    }

    protected function createTractor(int $odometer = 100000): FleetUnit
    {
        return FleetUnit::create([
            'fleet_id' => Fleet::first()->id,
            'base_id' => Base::first()->id,
            'unit_type_id' => UnitType::where('code', 'TRACTOR')->first()->id,
            'unit_configuration_id' => UnitConfiguration::where('code', '6X4')->first()->id,
            'plate' => 'TST'.rand(100, 999),
            'current_odometer' => $odometer,
            'status' => 'ACTIVA',
        ]);
    }

    protected function createTrailer(): FleetUnit
    {
        return FleetUnit::create([
            'fleet_id' => Fleet::first()->id,
            'base_id' => Base::first()->id,
            'unit_type_id' => UnitType::where('code', 'SEMIRREMOLQUE')->first()->id,
            'unit_configuration_id' => UnitConfiguration::where('code', '3E-D')->first()->id,
            'plate' => 'TRL'.rand(100, 999),
            'current_odometer' => 0,
            'status' => 'ACTIVA',
        ]);
    }

    protected function createLinealTrailer(int $tireWidth = 385, string $config = '3E-S'): FleetUnit
    {
        return FleetUnit::create([
            'fleet_id' => Fleet::first()->id,
            'base_id' => Base::first()->id,
            'unit_type_id' => UnitType::where('code', 'TANQUE')->first()->id,
            'unit_configuration_id' => UnitConfiguration::where('code', $config)->first()->id,
            'plate' => 'TNK'.rand(100, 999),
            'current_odometer' => 0,
            'status' => 'ACTIVA',
            'specs' => ['tire_width' => $tireWidth],
        ]);
    }

    protected function purchaseTires(int $quantity = 4, int $first = 40000, ?string $modelCode = null, ?string $sizeCode = null): array
    {
        $model = $modelCode
            ? TireModel::where('code', $modelCode)->firstOrFail()
            : TireModel::first();
        $size = $sizeCode
            ? TireSize::where('code', $sizeCode)->firstOrFail()
            : ($model->sizes()->orderBy('code')->first() ?: TireSize::first());

        $purchase = app(PurchaseService::class)->create([
            'supplier_id' => Supplier::first()->id,
            'base_id' => Base::first()->id,
            'purchased_at' => now()->toDateString(),
            'items' => [[
                'tire_brand_id' => $model->tire_brand_id,
                'tire_model_id' => $model->id,
                'tire_size_id' => $size->id,
                'quantity' => $quantity,
                'first_number' => $first,
            ]],
        ], $this->admin);

        app(PurchaseService::class)->confirm($purchase, $this->admin);

        return Tire::where('individual_number', '>=', $first)->orderBy('individual_number')->get()->all();
    }
}
