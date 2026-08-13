<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fleet extends Model
{
    protected $fillable = ['name', 'code', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function bases(): BelongsToMany
    {
        return $this->belongsToMany(Base::class, 'fleet_base');
    }

    public function units(): HasMany
    {
        return $this->hasMany(FleetUnit::class);
    }
}
