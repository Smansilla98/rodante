<?php

namespace App\Policies;

use App\Models\Tire;
use App\Models\User;
use App\Support\AccessScope;

class TirePolicy
{
    public function view(User $user, Tire $tire): bool
    {
        return AccessScope::canViewTire($user, $tire);
    }

    public function update(User $user, Tire $tire): bool
    {
        return AccessScope::canViewTire($user, $tire)
            && $user->role->canManageAbm();
    }

    /** Incidencia, medición, volver a stock. */
    public function write(User $user, Tire $tire): bool
    {
        return AccessScope::canViewTire($user, $tire)
            && $user->role->canWrite();
    }

    public function retire(User $user, Tire $tire): bool
    {
        return AccessScope::canViewTire($user, $tire)
            && $user->role->canRetireOrRecap();
    }
}
