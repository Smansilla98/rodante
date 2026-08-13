<?php

namespace App\Services;

use App\Enums\LocationKind;
use App\Enums\TireStatus;
use App\Models\Tire;
use App\Models\TireAssignmentSegment;
use App\Models\TireCurrentLocation;

class LocationService
{
    public function place(
        Tire $tire,
        LocationKind $kind,
        ?int $baseId = null,
        ?int $unitId = null,
        ?int $positionId = null,
    ): TireCurrentLocation {
        $status = TireStatus::from($kind->value);

        $location = TireCurrentLocation::updateOrCreate(
            ['tire_id' => $tire->id],
            [
                'location_kind' => $kind,
                'base_id' => $baseId,
                'unit_id' => $unitId,
                'position_id' => $positionId,
            ]
        );

        $tire->update(['status' => $status]);

        return $location;
    }

    public function refreshAccumulatedKm(Tire $tire): void
    {
        $km = (int) TireAssignmentSegment::query()
            ->whereHas('assignment', fn ($q) => $q->where('tire_id', $tire->id))
            ->where('counts_km', true)
            ->sum('km_delta');

        $tire->update(['accumulated_km' => $km]);

        $lifecycle = $tire->currentLifecycle;
        if ($lifecycle) {
            $lifeKm = (int) TireAssignmentSegment::query()
                ->whereHas('assignment', fn ($q) => $q->where('tire_lifecycle_id', $lifecycle->id))
                ->where('counts_km', true)
                ->sum('km_delta');
            $lifecycle->update(['km_in_life' => $lifeKm]);
        }
    }
}
