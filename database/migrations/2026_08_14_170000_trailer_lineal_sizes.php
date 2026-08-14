<?php

use App\Models\FleetUnit;
use App\Models\MeasurementZone;
use App\Models\TireBrand;
use App\Models\TireModel;
use App\Models\TireSize;
use App\Models\UnitConfiguration;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $size = TireSize::firstOrCreate(
            ['code' => '385/65 R22.5'],
            [
                'alias' => 'Gomón lineal',
                'width_mm' => 385,
                'aspect_ratio' => 65,
                'rim_inches' => 22.5,
                'uneven_wear_threshold_mm' => 3,
                'is_active' => true,
            ]
        );
        if ($size->zones()->doesntExist()) {
            foreach ([
                ['FLANCO_IZQ', 'Flanco izquierdo', 1],
                ['FLANCO_DER', 'Flanco derecho', 2],
                ['CENTRAL', 'Central', 3],
                ['PERIMETRAL', 'Alrededor', 4],
            ] as [$code, $name, $order]) {
                MeasurementZone::create([
                    'tire_size_id' => $size->id,
                    'code' => $code,
                    'name' => $name,
                    'sort_order' => $order,
                ]);
            }
        }

        $pirelli = TireBrand::where('name', 'Pirelli')->first();
        if ($pirelli && ! TireModel::where('code', 'FR:01')->exists()) {
            $model = TireModel::create([
                'tire_brand_id' => $pirelli->id,
                'code' => 'FR:01',
                'name' => 'Arrastre lineal',
                'application' => 'ARRASTRE',
                'is_active' => true,
            ]);
            $model->sizes()->sync(TireSize::pluck('id'));
        } elseif ($size->wasRecentlyCreated && ($model = TireModel::where('code', 'FR:01')->first())) {
            $model->sizes()->syncWithoutDetaching([$size->id]);
        }

        foreach (UnitConfiguration::where('applies_to', 'TRAILER')->get() as $cfg) {
            if (str_ends_with((string) $cfg->code, '-S')) {
                $cfg->name = str_replace(['rodado simple', 'Rodado simple'], 'lineal', $cfg->name);
                $cfg->description = str_replace('rodado simple', 'lineal (una cubierta por lado, 295 o 385 según la unidad)', (string) $cfg->description);
                $cfg->save();
                foreach ($cfg->positions()->where('axle_role', 'ARRASTRE')->whereNull('dual')->where('is_spare', false)->get() as $position) {
                    if ($position->side === 'IZQ') {
                        $position->update(['name' => 'Eje '.$position->axle_number.' — Lineal izquierdo']);
                    }
                    if ($position->side === 'DER') {
                        $position->update(['name' => 'Eje '.$position->axle_number.' — Lineal derecho']);
                    }
                }
            }
        }

        FleetUnit::query()
            ->whereHas('type', fn ($q) => $q->where('has_odometer', false))
            ->get()
            ->each(function (FleetUnit $unit) {
                $specs = $unit->specs ?? [];
                if (! empty($specs['tire_width'])) {
                    return;
                }
                $isLineal = $unit->configuration
                    && $unit->configuration->positions->where('is_spare', false)->whereNotNull('dual')->isEmpty();
                $specs['tire_width'] = $isLineal ? 385 : 295;
                $unit->update(['specs' => $specs]);
            });
    }

    public function down(): void
    {
        //
    }
};
