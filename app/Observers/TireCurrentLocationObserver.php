<?php

namespace App\Observers;

use App\Models\Tire;
use App\Models\TireCurrentLocation;
use App\Services\IntegrityService;

class TireCurrentLocationObserver
{
    public function __construct(private IntegrityService $integrity) {}

    public function saved(TireCurrentLocation $location): void
    {
        $this->bump($location->tire_id);
    }

    public function deleted(TireCurrentLocation $location): void
    {
        $this->bump($location->tire_id);
    }

    private function bump(int $tireId): void
    {
        $companyId = Tire::query()->whereKey($tireId)->value('company_id');
        $this->integrity->invalidateCompany($companyId ? (int) $companyId : null);
    }
}
