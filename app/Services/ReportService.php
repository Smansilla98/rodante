<?php

namespace App\Services;

use App\Models\FleetUnit;
use App\Models\Tire;
use App\Models\TireIncident;
use App\Models\TireMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function tireHistory(Tire $tire): Tire
    {
        return $tire->load([
            'brand', 'model', 'size', 'currentLocation.unit', 'currentLocation.position',
            'lifecycles', 'movements.fromUnit', 'movements.toUnit', 'movements.fromPosition',
            'movements.toPosition', 'movements.user', 'incidents.user',
            'measurements.readings.zone', 'assignments.segments.odometerUnit',
        ]);
    }

    public function unitHistory(FleetUnit $unit): Collection
    {
        return TireMovement::query()
            ->with(['tire.brand', 'tire.model', 'fromPosition', 'toPosition', 'user'])
            ->where(fn ($q) => $q->where('from_unit_id', $unit->id)->orWhere('to_unit_id', $unit->id))
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();
    }

    public function kilometersByTire(): Collection
    {
        return Tire::query()
            ->with(['brand', 'model', 'size'])
            ->withCount([
                'lifecycles',
                'incidents as recaps_count' => fn ($q) => $q->where('type', 'RECAPADO'),
                'incidents as repairs_count' => fn ($q) => $q->where('type', 'REPARACION'),
            ])
            ->orderByDesc('accumulated_km')
            ->get();
    }

    public function consumptionByModel(): Collection
    {
        return Tire::query()
            ->join('tire_brands', 'tire_brands.id', '=', 'tires.tire_brand_id')
            ->join('tire_models', 'tire_models.id', '=', 'tires.tire_model_id')
            ->select(
                'tire_brands.name as brand',
                'tire_models.code as model',
                DB::raw('count(*) as purchased'),
                DB::raw("sum(case when tires.status = 'INSTALADA' then 1 else 0 end) as installed"),
                DB::raw("sum(case when tires.status = 'STOCK' then 1 else 0 end) as stock"),
                DB::raw("sum(case when tires.status = 'DE_BAJA' then 1 else 0 end) as retired"),
                DB::raw('avg(tires.accumulated_km) as avg_km'),
            )
            ->groupBy('tire_brands.name', 'tire_models.code')
            ->orderBy('brand')
            ->get();
    }

    public function incidents(): Collection
    {
        return TireIncident::query()
            ->select('type', DB::raw('count(*) as total'), DB::raw('count(distinct tire_id) as tires'))
            ->groupBy('type')
            ->get();
    }
}
