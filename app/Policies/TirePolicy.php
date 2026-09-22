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

    /**
     * Incidencia, medición, volver a stock.
     *
     * Este gate autoriza la categoría general de "escribir sobre esta cubierta".
     * Cuando la acción puntual es un retorno a stock marcado como recapado
     * (TireOperationService::returnToStock($tire, ..., asRecap: true)), ese
     * método exige además canRetireOrRecap() — a propósito no se duplica esa
     * regla más fina acá, para no tener dos lugares que puedan desincronizarse
     * sobre CUÁNDO se aplica (solo aplica si asRecap=true). Ver
     * docs/AUDIT_OT_STOCK_RECAPADO.md, INC-08.
     */
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
