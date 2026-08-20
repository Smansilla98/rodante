<?php

namespace App\Observers;

use App\Models\Tire;
use App\Services\IntegrityService;
use Illuminate\Support\Str;

class TireObserver
{
    public function __construct(private IntegrityService $integrity) {}

    public function creating(Tire $tire): void
    {
        if (! $tire->public_token) {
            $tire->public_token = (string) Str::uuid();
        }
        if (! $tire->company_id && $tire->tire_purchase_item_id) {
            $purchaseCompany = $tire->purchaseItem?->purchase?->company_id;
            if ($purchaseCompany) {
                $tire->company_id = $purchaseCompany;
            }
        }
        if (! $tire->company_id) {
            $tire->company_id = auth()->user()?->company_id;
        }
    }

    public function saved(Tire $tire): void
    {
        $this->integrity->invalidateForTire($tire);
    }

    public function deleted(Tire $tire): void
    {
        $this->integrity->invalidateForTire($tire);
    }
}
