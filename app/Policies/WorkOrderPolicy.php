<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrder;
use App\Support\AccessScope;

class WorkOrderPolicy
{
    public function view(User $user, WorkOrder $workOrder): bool
    {
        return AccessScope::canViewWorkOrder($user, $workOrder);
    }

    public function create(User $user): bool
    {
        return $user->role->canWrite();
    }

    /** Enviar, cerrar, cancelar. */
    public function manage(User $user, WorkOrder $workOrder): bool
    {
        return AccessScope::canViewWorkOrder($user, $workOrder)
            && $user->role->canWrite();
    }
}
