<?php

namespace App\Observers;

use App\Models\Tire;
use App\Models\TireAssignment;
use App\Models\TireAssignmentSegment;
use App\Services\IntegrityService;

class TireAssignmentSegmentObserver
{
    public function __construct(private IntegrityService $integrity) {}

    public function saving(TireAssignmentSegment $segment): void
    {
        $segment->open_assignment_id = $segment->ended_at ? null : $segment->tire_assignment_id;
        if ($segment->ended_at !== null) {
            $segment->open_key = null;
        }
    }

    public function saved(TireAssignmentSegment $segment): void
    {
        $this->bump($segment);
    }

    public function deleted(TireAssignmentSegment $segment): void
    {
        $this->bump($segment);
    }

    private function bump(TireAssignmentSegment $segment): void
    {
        $tireId = TireAssignment::query()->whereKey($segment->tire_assignment_id)->value('tire_id');
        if (! $tireId) {
            return;
        }
        $companyId = Tire::query()->whereKey($tireId)->value('company_id');
        $this->integrity->invalidateCompany($companyId ? (int) $companyId : null);
    }
}
