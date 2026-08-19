<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CostEntry extends Model
{
    use Concerns\BelongsToCompany;

    protected $fillable = [
        'company_id', 'category', 'amount', 'currency', 'costable_type', 'costable_id',
        'tire_id', 'notes', 'user_id', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    public function costable(): MorphTo
    {
        return $this->morphTo();
    }

    public function tire(): BelongsTo
    {
        return $this->belongsTo(Tire::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categoryLabel(): string
    {
        return match ($this->category) {
            'PURCHASE' => 'Compra',
            'RECAP' => 'Recapado',
            'REPAIR' => 'Reparación',
            'WORK_ORDER' => 'Orden de trabajo',
            'SERVICE' => 'Servicio',
            default => 'Otro',
        };
    }
}
