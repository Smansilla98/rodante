<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TireSize extends Model
{
    protected $fillable = [
        'code', 'alias', 'width_mm', 'aspect_ratio', 'rim_inches',
        'uneven_wear_threshold_mm', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'rim_inches' => 'decimal:1',
        ];
    }

    public function displayName(): string
    {
        return $this->alias ? "{$this->code} ({$this->alias})" : $this->code;
    }

    public function zones(): HasMany
    {
        return $this->hasMany(MeasurementZone::class)->orderBy('sort_order');
    }

    public function models(): BelongsToMany
    {
        return $this->belongsToMany(TireModel::class, 'tire_model_sizes');
    }

    public function tires(): HasMany
    {
        return $this->hasMany(Tire::class);
    }
}
