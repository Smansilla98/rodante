<?php

namespace App\Models;

use App\Enums\LocationKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TireCurrentLocation extends Model
{
    protected $fillable = [
        'tire_id', 'location_kind', 'base_id', 'unit_id', 'position_id',
    ];

    protected function casts(): array
    {
        return ['location_kind' => LocationKind::class];
    }

    public function tire(): BelongsTo
    {
        return $this->belongsTo(Tire::class);
    }

    public function base(): BelongsTo
    {
        return $this->belongsTo(Base::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(FleetUnit::class, 'unit_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(UnitPosition::class, 'position_id');
    }
}
