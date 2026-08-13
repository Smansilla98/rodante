<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TireAssignment extends Model
{
    protected $fillable = [
        'tire_id', 'tire_lifecycle_id', 'unit_id', 'start_position_id',
        'end_position_id', 'counts_km', 'started_at', 'ended_at', 'open_key',
    ];

    protected function casts(): array
    {
        return [
            'counts_km' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function tire(): BelongsTo
    {
        return $this->belongsTo(Tire::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(FleetUnit::class, 'unit_id');
    }

    public function startPosition(): BelongsTo
    {
        return $this->belongsTo(UnitPosition::class, 'start_position_id');
    }

    public function endPosition(): BelongsTo
    {
        return $this->belongsTo(UnitPosition::class, 'end_position_id');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(TireAssignmentSegment::class);
    }

    public function openSegment(): HasOne
    {
        return $this->hasOne(TireAssignmentSegment::class)->whereNull('ended_at');
    }
}
