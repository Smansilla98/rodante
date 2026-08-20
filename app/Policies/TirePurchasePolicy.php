<?php

namespace App\Policies;

use App\Models\TirePurchase;
use App\Models\User;
use App\Support\AccessScope;

class TirePurchasePolicy
{
    public function view(User $user, TirePurchase $purchase): bool
    {
        $ok = TirePurchase::query()->whereKey($purchase->id);
        AccessScope::purchases($ok, $user);

        return $ok->exists();
    }

    public function create(User $user): bool
    {
        return $user->role->canWrite();
    }

    public function update(User $user, TirePurchase $purchase): bool
    {
        return $this->view($user, $purchase) && $user->role->canWrite();
    }

    public function confirm(User $user, TirePurchase $purchase): bool
    {
        return $this->view($user, $purchase) && $user->role->canWrite();
    }

    public function delete(User $user, TirePurchase $purchase): bool
    {
        return $this->view($user, $purchase) && $user->role->canManageAbm();
    }
}
