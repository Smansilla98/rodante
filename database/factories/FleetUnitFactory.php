<?php

namespace Database\Factories;

use App\Enums\UnitStatus;
use App\Models\Base;
use App\Models\Company;
use App\Models\Fleet;
use App\Models\FleetUnit;
use App\Models\UnitConfiguration;
use App\Models\UnitType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FleetUnit>
 */
class FleetUnitFactory extends Factory
{
    protected $model = FleetUnit::class;

    public function definition(): array
    {
        $type = UnitType::query()->where('code', 'TRACTOR')->first()
            ?: UnitType::query()->firstOrFail();
        $config = UnitConfiguration::query()->where('code', '6X4')->first()
            ?: UnitConfiguration::query()->firstOrFail();

        return [
            'fleet_id' => Fleet::query()->value('id') ?? Fleet::query()->create([
                'name' => 'Flota test',
                'code' => 'TST',
                'is_active' => true,
            ])->id,
            'base_id' => Base::query()->value('id') ?? Base::query()->create([
                'name' => 'Base test',
                'code' => 'BT',
                'is_active' => true,
            ])->id,
            'unit_type_id' => $type->id,
            'unit_configuration_id' => $config->id,
            'plate' => strtoupper(fake()->unique()->bothify('??###??')),
            'current_odometer' => 100000,
            'status' => UnitStatus::Activa,
            'company_id' => Company::demo()->id,
        ];
    }
}
