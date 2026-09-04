<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderItem extends Model
{
    protected $fillable = [
        'work_order_id', 'tire_id', 'open_tire_id',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function tire(): BelongsTo
    {
        return $this->belongsTo(Tire::class);
    }
}
