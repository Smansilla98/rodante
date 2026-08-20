<?php

namespace App\Services;

use App\Enums\TireStatus;
use App\Models\Tire;
use App\Models\TireAssignment;
use App\Models\TireAssignmentSegment;
use App\Models\TireCurrentLocation;
use App\Models\User;
use App\Support\AccessScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IntegrityService
{
    public function findings(?User $user = null): Collection
    {
        $user ??= auth()->user();
        $tires = Tire::query();
        if ($user) {
            AccessScope::tires($tires, $user);
        }
        $ids = (clone $tires)->select('id');

        $items = collect();

        $withoutLocation = (clone $tires)
            ->whereDoesntHave('currentLocation')
            ->where('status', '!=', TireStatus::DeBaja)
            ->limit(50)
            ->get(['id', 'individual_number', 'tire_model_id']);
        foreach ($withoutLocation as $tire) {
            $items->push($this->row('NO_LOCATION', $tire, 'No tiene ubicación actual. Debería haber una sola fila en stock, unidad o taller.'));
        }

        $installedOffUnit = TireCurrentLocation::query()
            ->whereIn('tire_id', $ids)
            ->whereIn('location_kind', ['INSTALADA', 'AUXILIO'])
            ->where(function ($q) {
                $q->whereNull('unit_id')->orWhereNull('position_id');
            })
            ->with('tire')
            ->limit(50)
            ->get();
        foreach ($installedOffUnit as $loc) {
            if ($loc->tire) {
                $items->push($this->row('MOUNTED_WITHOUT_SLOT', $loc->tire, 'Figura montada pero no tiene unidad o posición.'));
            }
        }

        $stockOnUnit = TireCurrentLocation::query()
            ->whereIn('tire_id', $ids)
            ->where('location_kind', 'STOCK')
            ->whereNotNull('unit_id')
            ->with('tire')
            ->limit(50)
            ->get();
        foreach ($stockOnUnit as $loc) {
            if ($loc->tire) {
                $items->push($this->row('STOCK_ON_UNIT', $loc->tire, 'Está en STOCK pero sigue apuntando a una unidad.'));
            }
        }

        $openCounts = TireAssignment::query()
            ->whereNull('ended_at')
            ->whereIn('tire_id', $ids)
            ->select('tire_id', DB::raw('count(*) as total'))
            ->groupBy('tire_id')
            ->having('total', '>', 1)
            ->limit(50)
            ->get();
        foreach ($openCounts as $row) {
            $tire = Tire::query()->find($row->tire_id);
            if ($tire) {
                $items->push($this->row('MULTI_OPEN_ASSIGNMENT', $tire, 'Tiene '.$row->total.' assignments abiertos. Solo puede haber uno.'));
            }
        }

        $openWrongStatus = TireAssignment::query()
            ->whereNull('ended_at')
            ->whereIn('tire_id', $ids)
            ->whereHas('tire', fn ($q) => $q->whereNotIn('status', [TireStatus::Instalada, TireStatus::Auxilio]))
            ->with('tire')
            ->limit(50)
            ->get();
        foreach ($openWrongStatus as $assignment) {
            if ($assignment->tire) {
                $items->push($this->row('OPEN_ASSIGNMENT_WRONG_STATUS', $assignment->tire, 'Tiene assignment abierto pero el estado no es instalada ni auxilio.'));
            }
        }

        $mountedWithoutAssignment = (clone $tires)
            ->whereIn('status', [TireStatus::Instalada, TireStatus::Auxilio])
            ->whereDoesntHave('openAssignment')
            ->limit(50)
            ->get(['id', 'individual_number', 'tire_model_id']);
        foreach ($mountedWithoutAssignment as $tire) {
            $items->push($this->row('MOUNTED_WITHOUT_ASSIGNMENT', $tire, 'Está montada y no hay assignment abierto. El kilometraje puede quedar colgado.'));
        }

        $negativeKm = TireAssignmentSegment::query()
            ->where('km_delta', '<', 0)
            ->whereHas('assignment', fn ($q) => $q->whereIn('tire_id', $ids))
            ->with('assignment.tire')
            ->limit(50)
            ->get();
        foreach ($negativeKm as $segment) {
            $tire = $segment->assignment?->tire;
            if ($tire) {
                $items->push($this->row('NEGATIVE_KM', $tire, 'Hay un tramo con km negativo ('.$segment->km_delta.').'));
            }
        }

        return $items->unique(fn ($row) => $row['code'].'-'.$row['tire_id'])->values();
    }

    public function count(?User $user = null): int
    {
        return $this->findings($user)->count();
    }

    private function row(string $code, Tire $tire, string $message): array
    {
        return [
            'code' => $code,
            'tire_id' => $tire->id,
            'label' => $tire->displayName(),
            'message' => $message,
            'url' => route('tires.show', $tire),
        ];
    }
}
