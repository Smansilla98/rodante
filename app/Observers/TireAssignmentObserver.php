<?php

namespace App\Observers;

use App\Models\Tire;
use App\Models\TireAssignment;
use App\Services\IntegrityService;

class TireAssignmentObserver
{
    public function __construct(private IntegrityService $integrity) {}

    public function saving(TireAssignment $assignment): void
    {
        $assignment->open_tire_id = $assignment->ended_at ? null : $assignment->tire_id;
        if ($assignment->ended_at === null) {
            $assignment->open_key = $assignment->open_key ?: $assignment->tire_id;
        } else {
            $assignment->open_key = null;
        }
    }

    public function saved(TireAssignment $assignment): void
    {
        $this->bump($assignment->tire_id);
    }

    public function deleted(TireAssignment $assignment): void
    {
        $this->bump($assignment->tire_id);
    }

    private function bump(int $tireId): void
    {
        $companyId = Tire::query()->whereKey($tireId)->value('company_id');
        $this->integrity->invalidateCompany($companyId ? (int) $companyId : null);
    }
}
