<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TireMeasurementReading extends Model
{
    public $timestamps = false;

    protected $fillable = ['tire_measurement_id', 'measurement_zone_id', 'millimeters'];

    protected function casts(): array
    {
        return ['millimeters' => 'decimal:1'];
    }

    public function measurement(): BelongsTo
    {
        return $this->belongsTo(TireMeasurement::class, 'tire_measurement_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(MeasurementZone::class, 'measurement_zone_id');
    }
}
