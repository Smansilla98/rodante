<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TireAssignmentSegment extends Model
{
    protected $fillable = [
        'tire_assignment_id', 'odometer_unit_id', 'start_odometer', 'end_odometer',
        'km_delta', 'counts_km', 'started_at', 'ended_at', 'open_key',
    ];

    protected function casts(): array
    {
        return [
            'counts_km' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TireAssignment::class, 'tire_assignment_id');
    }

    public function odometerUnit(): BelongsTo
    {
        return $this->belongsTo(FleetUnit::class, 'odometer_unit_id');
    }
}
