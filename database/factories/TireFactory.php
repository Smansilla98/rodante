<?php

namespace Database\Factories;

use App\Enums\TireCondition;
use App\Enums\TireStatus;
use App\Models\Company;
use App\Models\Tire;
use App\Models\TireModel;
use App\Models\TireSize;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tire>
 */
class TireFactory extends Factory
{
    protected $model = Tire::class;

    public function definition(): array
    {
        $model = TireModel::query()->firstOrFail();
        $size = $model->sizes()->first() ?: TireSize::query()->firstOrFail();

        return [
            'individual_number' => fake()->unique()->numberBetween(90000, 99999),
            'tire_brand_id' => $model->tire_brand_id,
            'tire_model_id' => $model->id,
            'tire_size_id' => $size->id,
            'status' => TireStatus::Stock,
            'condition' => TireCondition::Nueva,
            'company_id' => Company::demo()->id,
            'accumulated_km' => 0,
            'purchased_at' => now()->toDateString(),
        ];
    }
}
