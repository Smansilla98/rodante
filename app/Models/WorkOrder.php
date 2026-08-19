<?php

namespace App\Models;

use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrder extends Model
{
    use Concerns\BelongsToCompany;

    protected $fillable = [
        'company_id', 'number', 'tire_id', 'open_tire_id', 'retread_shop_id', 'type', 'status',
        'cost', 'notes', 'opened_by', 'closed_by', 'sent_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => WorkOrderType::class,
            'status' => WorkOrderStatus::class,
            'cost' => 'decimal:2',
            'sent_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function tire(): BelongsTo
    {
        return $this->belongsTo(Tire::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(RetreadShop::class, 'retread_shop_id');
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
