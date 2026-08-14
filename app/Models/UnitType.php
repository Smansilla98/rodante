<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitType extends Model
{
    protected $fillable = ['code', 'name', 'has_odometer', 'is_active'];

    protected function casts(): array
    {
        return [
            'has_odometer' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function units(): HasMany
    {
        return $this->hasMany(FleetUnit::class);
    }

    public function isPowered(): bool
    {
        return (bool) $this->has_odometer;
    }

    public function sheetLabel(): string
    {
        return match ($this->code) {
            'TRACTOR', 'CAMION_TRACTOR' => 'TRACTOR',
            'CAMION' => 'CAMIÓN',
            'TANQUE' => 'TANQUE',
            'SEMIRREMOLQUE' => 'SEMIRREMOLQUE',
            'BATEA' => 'BATEA',
            default => mb_strtoupper($this->name),
        };
    }

    public function sheetPrefix(): string
    {
        return match ($this->code) {
            'TRACTOR', 'CAMION_TRACTOR' => 'TC',
            'CAMION' => 'CM',
            'SEMIRREMOLQUE' => 'SR',
            'TANQUE' => 'TQ',
            'BATEA' => 'BT',
            default => 'UN',
        };
    }
}
