<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TireMeasurementReading extends Model
{

    use BelongsToCompany;
    public $timestamps = false;

    protected $fillable = [
        'company_id','tire_measurement_id', 'measurement_zone_id', 'millimeters'];

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
