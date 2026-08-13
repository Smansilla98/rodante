<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TireMeasurement extends Model
{
    protected $fillable = [
        'tire_id', 'measured_at', 'unit_id', 'odometer',
        'user_id', 'raises_alert', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'measured_at' => 'datetime',
            'raises_alert' => 'boolean',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(TireMeasurementReading::class);
    }
}
