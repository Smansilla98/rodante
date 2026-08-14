<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitPosition extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'unit_configuration_id', 'code', 'name', 'axle_number', 'axle_role',
        'side', 'dual', 'is_spare', 'is_liftable', 'is_self_steer',
        'grid_row', 'grid_col', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_spare' => 'boolean',
            'is_liftable' => 'boolean',
            'is_self_steer' => 'boolean',
        ];
    }

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(UnitConfiguration::class, 'unit_configuration_id');
    }

    public function sheetCode(string $prefix): string
    {
        if ($this->is_spare) {
            return $prefix.'-AUX';
        }

        $code = $prefix.'-E'.$this->axle_number.'-'.$this->side;
        if ($this->dual) {
            $code .= '-'.$this->dual;
        }

        return $code;
    }

    public function axleRole(bool $hasOdometer = true, bool $isSteer = false): string
    {
        if ($this->is_spare || $this->axle_role === 'AUXILIO') {
            return 'Auxilio';
        }

        $label = match ($this->axle_role) {
            'DIRECCION' => 'Dirección',
            'TRACCION' => 'Tracción',
            'DIRECCIONAL' => 'Direccional',
            default => 'Arrastre',
        };

        if ($this->is_liftable) {
            $label .= ' elevable';
        }
        if ($this->is_self_steer) {
            $label .= ' autodir.';
        }

        return $label;
    }
}
