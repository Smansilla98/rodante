<?php

namespace App\Observers;

use App\Models\TireAssignment;

class TireAssignmentObserver
{
    public function saving(TireAssignment $assignment): void
    {
        $assignment->open_tire_id = $assignment->ended_at ? null : $assignment->tire_id;
        if ($assignment->ended_at === null) {
            $assignment->open_key = $assignment->open_key ?: $assignment->tire_id;
        } else {
            $assignment->open_key = null;
        }
    }
}
