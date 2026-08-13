<?php

namespace App\Models;

use App\Enums\IncidentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TireIncident extends Model
{
    protected $fillable = [
        'tire_id', 'type', 'occurred_at', 'unit_id', 'position_id',
        'odometer', 'user_id', 'description', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => IncidentType::class,
            'occurred_at' => 'datetime',
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

    public function position(): BelongsTo
    {
        return $this->belongsTo(UnitPosition::class, 'position_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
