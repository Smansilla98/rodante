<?php

namespace App\Observers;

use App\Models\TireAssignmentSegment;

class TireAssignmentSegmentObserver
{
    public function saving(TireAssignmentSegment $segment): void
    {
        $segment->open_assignment_id = $segment->ended_at ? null : $segment->tire_assignment_id;
        if ($segment->ended_at !== null) {
            $segment->open_key = null;
        }
    }
}
