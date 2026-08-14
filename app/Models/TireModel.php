<?php

namespace App\Models;

use App\Enums\TireApplication;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TireModel extends Model
{
    protected $fillable = ['tire_brand_id', 'code', 'name', 'application', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'application' => TireApplication::class,
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(TireBrand::class, 'tire_brand_id');
    }

    public function sizes(): BelongsToMany
    {
        return $this->belongsToMany(TireSize::class, 'tire_model_sizes');
    }

    public function tires(): HasMany
    {
        return $this->hasMany(Tire::class);
    }
}
