<?php

namespace App\Models;

use App\Enums\InventoryLineDelta;
use App\Enums\LocationKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLine extends Model
{
    protected $fillable = [
        'inventory_session_id', 'tire_id', 'expected_kind', 'expected_base_id', 'expected_unit_id',
        'in_snapshot', 'found', 'delta', 'observed_kind', 'observed_base_id',
        'scanned_at', 'scanned_by', 'adjustment_applied', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'expected_kind' => LocationKind::class,
            'observed_kind' => LocationKind::class,
            'delta' => InventoryLineDelta::class,
            'in_snapshot' => 'boolean',
            'found' => 'boolean',
            'adjustment_applied' => 'boolean',
            'scanned_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(InventorySession::class, 'inventory_session_id');
    }

    public function tire(): BelongsTo
    {
        return $this->belongsTo(Tire::class);
    }

    public function expectedBase(): BelongsTo
    {
        return $this->belongsTo(Base::class, 'expected_base_id');
    }

    public function observedBase(): BelongsTo
    {
        return $this->belongsTo(Base::class, 'observed_base_id');
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
