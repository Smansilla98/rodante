<?php

namespace App\Policies;

use App\Models\InventorySession;
use App\Models\User;
use App\Support\AccessScope;

class InventorySessionPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->company_id;
    }

    public function view(User $user, InventorySession $session): bool
    {
        if ((int) $session->company_id !== (int) $user->company_id) {
            return false;
        }
        if (AccessScope::seesEverything($user)) {
            return true;
        }

        return in_array((int) $session->base_id, AccessScope::visibleBaseIds($user), true);
    }

    public function create(User $user): bool
    {
        return $user->role->canWrite();
    }

    public function count(User $user, InventorySession $session): bool
    {
        return $this->view($user, $session) && $user->role->canWrite();
    }

    public function close(User $user, InventorySession $session): bool
    {
        return $this->view($user, $session)
            && ($user->role->canValidateOdometer() || $user->role->canChangeConfiguration());
    }

    public function adjust(User $user, InventorySession $session): bool
    {
        return $this->view($user, $session) && $user->role->canChangeConfiguration();
    }

    public function cancel(User $user, InventorySession $session): bool
    {
        return $this->close($user, $session);
    }
}
