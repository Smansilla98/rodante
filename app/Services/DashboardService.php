<?php

namespace App\Services;

use App\Enums\IncidentType;
use App\Enums\TireCondition;
use App\Enums\TireStatus;
use App\Models\FleetUnit;
use App\Models\Tire;
use App\Models\TireIncident;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function stats(): array
    {
        $byStatus = Tire::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $byCondition = Tire::query()
            ->select('condition', DB::raw('count(*) as total'))
            ->groupBy('condition')
            ->pluck('total', 'condition');

        $byBrand = Tire::query()
            ->join('tire_brands', 'tire_brands.id', '=', 'tires.tire_brand_id')
            ->select('tire_brands.name', DB::raw('count(*) as total'))
            ->groupBy('tire_brands.name')
            ->orderByDesc('total')
            ->get();

        $nearRetirement = Tire::query()
            ->with(['brand', 'model', 'size'])
            ->where('status', '!=', TireStatus::DeBaja)
            ->where(function ($q) {
                $q->where('accumulated_km', '>=', 80000)
                    ->orWhere('current_tread_min', '<=', 4);
            })
            ->orderByDesc('accumulated_km')
            ->limit(10)
            ->get();

        $incidents = TireIncident::query()
            ->select('type', DB::raw('count(*) as total'))
            ->groupBy('type')
            ->get()
            ->map(fn ($row) => [
                'type' => IncidentType::from($row->type)->label(),
                'total' => $row->total,
            ]);

        $unitsWithIncidents = TireIncident::query()
            ->whereNotNull('unit_id')
            ->select('unit_id', DB::raw('count(*) as total'))
            ->groupBy('unit_id')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                $unit = FleetUnit::find($row->unit_id);

                return [
                    'plate' => $unit?->plate ?? '—',
                    'total' => $row->total,
                ];
            });

        return [
            'total' => Tire::count(),
            'by_status' => $byStatus,
            'by_condition' => $byCondition,
            'by_brand' => $byBrand,
            'km_total' => (int) Tire::sum('accumulated_km'),
            'near_retirement' => $nearRetirement,
            'incidents' => $incidents,
            'units_with_incidents' => $unitsWithIncidents,
            'nuevas' => $byCondition[TireCondition::Nueva->value] ?? 0,
            'usadas' => $byCondition[TireCondition::Usada->value] ?? 0,
            'recapadas' => $byCondition[TireCondition::Recapada->value] ?? 0,
        ];
    }
}
