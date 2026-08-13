<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitCoupling extends Model
{
    protected $fillable = [
        'tractor_id', 'trailer_id', 'tractor_odometer_start', 'tractor_odometer_end',
        'coupled_at', 'uncoupled_at', 'user_id', 'notes',
        'open_trailer_key', 'open_tractor_key',
    ];

    protected function casts(): array
    {
        return [
            'coupled_at' => 'datetime',
            'uncoupled_at' => 'datetime',
        ];
    }

    public function tractor(): BelongsTo
    {
        return $this->belongsTo(FleetUnit::class, 'tractor_id');
    }

    public function trailer(): BelongsTo
    {
        return $this->belongsTo(FleetUnit::class, 'trailer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOpen(): bool
    {
        return $this->uncoupled_at === null;
    }
}
