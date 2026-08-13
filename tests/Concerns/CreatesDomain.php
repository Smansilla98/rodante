<?php

namespace Tests\Concerns;

use App\Enums\UserRole;
use App\Models\Base;
use App\Models\Fleet;
use App\Models\FleetUnit;
use App\Models\Supplier;
use App\Models\Tire;
use App\Models\TireBrand;
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
            'unit_type_id' => UnitType::where('has_odometer', true)->first()->id,
            'unit_configuration_id' => UnitConfiguration::where('code', '6X24-T')->first()->id,
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
            'unit_type_id' => UnitType::where('has_odometer', false)->first()->id,
            'unit_configuration_id' => UnitConfiguration::where('code', '6X24-A')->first()->id,
            'plate' => 'TRL'.rand(100, 999),
            'current_odometer' => 0,
            'status' => 'ACTIVA',
        ]);
    }

    protected function purchaseTires(int $quantity = 4, int $first = 40000): array
    {
        $purchase = app(PurchaseService::class)->create([
            'supplier_id' => Supplier::first()->id,
            'base_id' => Base::first()->id,
            'purchased_at' => now()->toDateString(),
            'items' => [[
                'tire_brand_id' => TireBrand::first()->id,
                'tire_model_id' => TireModel::first()->id,
                'tire_size_id' => TireSize::first()->id,
                'quantity' => $quantity,
                'first_number' => $first,
            ]],
        ], $this->admin);

        app(PurchaseService::class)->confirm($purchase, $this->admin);

        return Tire::where('individual_number', '>=', $first)->orderBy('individual_number')->get()->all();
    }
}
