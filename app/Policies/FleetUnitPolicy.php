<?php

namespace App\Policies;

use App\Models\FleetUnit;
use App\Models\User;
use App\Support\AccessScope;

class FleetUnitPolicy
{
    public function view(User $user, FleetUnit $unit): bool
    {
        return AccessScope::canViewUnit($user, $unit);
    }

    /** Planilla / operate. */
    public function operate(User $user, FleetUnit $unit): bool
    {
        return AccessScope::canViewUnit($user, $unit)
            && $user->role->canWrite();
    }

    public function couple(User $user, FleetUnit $unit): bool
    {
        return AccessScope::canViewUnit($user, $unit)
            && $user->role->canManageCouplings();
    }

    public function configure(User $user, FleetUnit $unit): bool
    {
        return AccessScope::canViewUnit($user, $unit)
            && $user->role->canChangeConfiguration();
    }

    public function manage(User $user, FleetUnit $unit): bool
    {
        return AccessScope::canViewUnit($user, $unit)
            && $user->role->canManageAbm();
    }
}
