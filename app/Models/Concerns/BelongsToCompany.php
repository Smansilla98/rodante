<?php

namespace App\Models\Concerns;

use App\Models\Company;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCompany
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function bootBelongsToCompany(): void
    {
        static::creating(function ($model) {
            if ($model->company_id) {
                return;
            }
            $user = auth()->user();
            if ($user?->company_id) {
                $model->company_id = $user->company_id;
            } elseif ($id = Company::query()->value('id')) {
                $model->company_id = $id;
            }
        });
    }
}
