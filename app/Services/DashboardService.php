<?php

namespace App\Services;

use App\Enums\IncidentType;
use App\Enums\TireCondition;
use App\Enums\TireStatus;
use App\Models\FleetUnit;
use App\Models\Tire;
use App\Models\TireIncident;
use App\Models\User;
use App\Support\AccessScope;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function stats(?User $user = null): array
    {
        $user ??= auth()->user();
        $tires = Tire::query();
        if ($user) {
            AccessScope::tires($tires, $user);
        }

        $byStatus = (clone $tires)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $byCondition = (clone $tires)
            ->select('condition', DB::raw('count(*) as total'))
            ->groupBy('condition')
            ->pluck('total', 'condition');

        $byBrand = (clone $tires)
            ->join('tire_brands', 'tire_brands.id', '=', 'tires.tire_brand_id')
            ->select('tire_brands.name', DB::raw('count(*) as total'))
            ->groupBy('tire_brands.name')
            ->orderByDesc('total')
            ->get();

        $nearRetirement = (clone $tires)
            ->with(['brand', 'model', 'size'])
            ->where('status', '!=', TireStatus::DeBaja)
            ->where(function ($q) {
                $q->where('accumulated_km', '>=', 80000)
                    ->orWhere('current_tread_min', '<=', 4);
            })
            ->orderByDesc('accumulated_km')
            ->limit(10)
            ->get();

        $criticalTread = (clone $tires)
            ->with(['brand', 'model', 'size'])
            ->where('status', '!=', TireStatus::DeBaja)
            ->where('current_tread_min', '<=', 4)
            ->orderBy('current_tread_min')
            ->limit(10)
            ->get();

        $inRepair = (clone $tires)
            ->with(['brand', 'model', 'size'])
            ->where('status', TireStatus::EnReparacion)
            ->orderBy('individual_number')
            ->limit(10)
            ->get();

        $incidents = TireIncident::query()
            ->when($user && ! AccessScope::seesEverything($user), function ($q) use ($tires) {
                $q->whereIn('tire_id', (clone $tires)->select('id'));
            })
            ->select('type', DB::raw('count(*) as total'))
            ->groupBy('type')
            ->get()
            ->map(fn ($row) => [
                'type' => $row->type instanceof IncidentType
                    ? $row->type->label()
                    : IncidentType::from($row->type)->label(),
                'total' => $row->total,
            ]);

        $unitsQuery = FleetUnit::query();
        if ($user) {
            AccessScope::units($unitsQuery, $user);
        }
        $unitIds = $unitsQuery->pluck('id');

        $unitsWithIncidents = TireIncident::query()
            ->whereNotNull('unit_id')
            ->when($unitIds->isNotEmpty() || ($user && ! AccessScope::seesEverything($user)), fn ($q) => $q->whereIn('unit_id', $unitIds))
            ->select('unit_id', DB::raw('count(*) as total'))
            ->groupBy('unit_id')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $plates = FleetUnit::query()->whereIn('id', $unitsWithIncidents->pluck('unit_id'))->pluck('plate', 'id');

        $unitsWithIncidents = $unitsWithIncidents->map(fn ($row) => [
            'id' => $row->unit_id,
            'plate' => $plates[$row->unit_id] ?? '—',
            'total' => $row->total,
        ]);

        return [
            'total' => (clone $tires)->count(),
            'by_status' => $byStatus,
            'by_condition' => $byCondition,
            'by_brand' => $byBrand,
            'km_total' => (int) (clone $tires)->sum('accumulated_km'),
            'near_retirement' => $nearRetirement,
            'near_retirement_count' => (clone $tires)->where('status', '!=', TireStatus::DeBaja)->where(function ($q) {
                $q->where('accumulated_km', '>=', 80000)->orWhere('current_tread_min', '<=', 4);
            })->count(),
            'critical_tread' => $criticalTread,
            'critical_tread_count' => (clone $tires)->where('status', '!=', TireStatus::DeBaja)->where('current_tread_min', '<=', 4)->count(),
            'in_repair' => $inRepair,
            'in_repair_count' => (int) ($byStatus[TireStatus::EnReparacion->value] ?? 0),
            'incidents' => $incidents,
            'units_with_incidents' => $unitsWithIncidents,
            'nuevas' => $byCondition[TireCondition::Nueva->value] ?? 0,
            'usadas' => $byCondition[TireCondition::Usada->value] ?? 0,
            'recapadas' => $byCondition[TireCondition::Recapada->value] ?? 0,
            'reparadas' => $byCondition[TireCondition::Reparada->value] ?? 0,
            'thresholds' => ['km' => 80000, 'mm' => 4],
        ];
    }
}
