<?php

namespace App\Services;

use App\Models\CostEntry;
use App\Models\Tire;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CostService
{
    public function record(
        User $user,
        string $category,
        float $amount,
        Model $costable,
        ?Tire $tire = null,
        ?string $notes = null,
    ): CostEntry {
        return CostEntry::create([
            'company_id' => $user->company_id,
            'category' => $category,
            'amount' => $amount,
            'currency' => 'ARS',
            'costable_type' => $costable::class,
            'costable_id' => $costable->getKey(),
            'tire_id' => $tire?->id,
            'notes' => $notes,
            'user_id' => $user->id,
            'occurred_at' => now(),
        ]);
    }
}
