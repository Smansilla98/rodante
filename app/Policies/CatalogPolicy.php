<?php

namespace App\Policies;

use App\Models\User;

class CatalogPolicy
{
    public function manage(User $user): bool
    {
        return $user->role->canManageCatalogs() || $user->role->canManageAbm();
    }
}
