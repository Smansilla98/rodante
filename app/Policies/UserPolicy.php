<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Administrador;
    }

    public function manage(User $user, User $target): bool
    {
        return $user->role === UserRole::Administrador
            && (int) $user->company_id === (int) $target->company_id;
    }
}
