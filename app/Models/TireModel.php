<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TireModel extends Model
{
    protected $fillable = ['tire_brand_id', 'code', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(TireBrand::class, 'tire_brand_id');
    }

    public function sizes(): BelongsToMany
    {
        return $this->belongsToMany(TireSize::class, 'tire_model_sizes');
    }
}
