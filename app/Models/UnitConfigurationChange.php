<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitConfigurationChange extends Model
{
    protected $fillable = [
        'unit_id', 'from_configuration_id', 'to_configuration_id',
        'reason', 'user_id', 'occurred_at', 'notes',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(FleetUnit::class, 'unit_id');
    }

    public function fromConfiguration(): BelongsTo
    {
        return $this->belongsTo(UnitConfiguration::class, 'from_configuration_id');
    }

    public function toConfiguration(): BelongsTo
    {
        return $this->belongsTo(UnitConfiguration::class, 'to_configuration_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
