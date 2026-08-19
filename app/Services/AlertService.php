<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\CompanyAlert;
use Illuminate\Support\Facades\Notification;

class AlertService
{
    public function notifyCompany(int $companyId, string $title, string $body, ?string $url = null, array $roles = []): void
    {
        $query = User::query()->where('company_id', $companyId)->where('is_active', true);
        if ($roles !== []) {
            $query->whereIn('role', $roles);
        }

        Notification::send($query->get(), new CompanyAlert($title, $body, $url));
    }
}
