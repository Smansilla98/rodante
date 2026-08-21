<?php

namespace App\Policies;

use App\Models\OdometerReading;
use App\Models\User;
use App\Support\AccessScope;

class OdometerReadingPolicy
{
    public function update(User $user, OdometerReading $reading): bool
    {
        $reading->loadMissing('unit');

        return $reading->unit !== null
            && $user->role->canValidateOdometer()
            && AccessScope::canViewUnit($user, $reading->unit);
    }
}
