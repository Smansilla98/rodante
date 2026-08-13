<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitPosition extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'unit_configuration_id', 'code', 'name', 'axle_number',
        'side', 'dual', 'is_spare', 'grid_row', 'grid_col', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_spare' => 'boolean'];
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

    public function axleRole(bool $hasOdometer, bool $isSteer = false): string
    {
        if ($this->is_spare) {
            return 'Auxilio';
        }
        if (! $hasOdometer) {
            return 'Arrastre';
        }

        return ($isSteer || $this->axle_number === 1) ? 'Dirección' : 'Tracción';
    }
}
