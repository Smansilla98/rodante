<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    public function log(string $action, ?Model $entity = null, ?array $old = null, ?array $new = null): AuditLog
    {
        $user = Auth::user();

        return AuditLog::create([
            'company_id' => $this->resolveCompanyId($user, $entity),
            'user_id' => $user?->id,
            'action' => $action,
            'entity_type' => $entity ? $entity::class : null,
            'entity_id' => $entity?->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
        ]);
    }

    private function resolveCompanyId(?User $user, ?Model $entity): ?int
    {
        if ($user?->company_id) {
            return (int) $user->company_id;
        }

        if ($entity && isset($entity->company_id) && $entity->company_id) {
            return (int) $entity->company_id;
        }

        return Company::query()->value('id');
    }
}
