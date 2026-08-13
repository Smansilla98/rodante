<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitConfiguration extends Model
{
    protected $fillable = [
        'code', 'name', 'family_code', 'applies_to',
        'axle_count', 'position_count', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function positions(): HasMany
    {
        return $this->hasMany(UnitPosition::class)->orderBy('sort_order');
    }
}
