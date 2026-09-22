<?php

namespace App\Policies;

use App\Enums\WorkOrderType;
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

    /**
     * Enviar, cerrar, cancelar.
     *
     * Una OT de Recapado exige el mismo permiso para cerrarla/cancelarla que
     * para abrirla (ver WorkOrderService::open, que ya exige canRetireOrRecap()
     * para type=Recapado). Antes solo se pedía canWrite() acá, lo que permitía
     * que un rol sin permiso de recapado cerrara una OT que no podría haber
     * abierto (docs/AUDIT_OT_STOCK_RECAPADO.md, INC-08).
     */
    public function manage(User $user, WorkOrder $workOrder): bool
    {
        if (! AccessScope::canViewWorkOrder($user, $workOrder) || ! $user->role->canWrite()) {
            return false;
        }

        if ($workOrder->type === WorkOrderType::Recapado) {
            return $user->role->canRetireOrRecap();
        }

        return true;
    }
}
