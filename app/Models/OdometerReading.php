<?php

namespace App\Models;

use App\Enums\OdometerStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OdometerReading extends Model
{
    protected $fillable = [
        'unit_id', 'value', 'status', 'recorded_by', 'validated_by',
        'validation_source', 'recorded_at', 'validated_at',
        'tire_operation_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => OdometerStatus::class,
            'recorded_at' => 'datetime',
            'validated_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(FleetUnit::class, 'unit_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
