<?php

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TireMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tire_id', 'tire_operation_id', 'type', 'occurred_at',
        'from_unit_id', 'from_position_id', 'from_odometer',
        'to_unit_id', 'to_position_id', 'to_odometer',
        'from_base_id', 'to_base_id', 'km_delta', 'counts_km',
        'reason_id', 'user_id', 'notes', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
            'counts_km' => 'boolean',
        ];
    }

    public function tire(): BelongsTo
    {
        return $this->belongsTo(Tire::class);
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(TireOperation::class, 'tire_operation_id');
    }

    public function fromUnit(): BelongsTo
    {
        return $this->belongsTo(FleetUnit::class, 'from_unit_id');
    }

    public function toUnit(): BelongsTo
    {
        return $this->belongsTo(FleetUnit::class, 'to_unit_id');
    }

    public function fromPosition(): BelongsTo
    {
        return $this->belongsTo(UnitPosition::class, 'from_position_id');
    }

    public function toPosition(): BelongsTo
    {
        return $this->belongsTo(UnitPosition::class, 'to_position_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(MovementReason::class, 'reason_id');
    }
}
