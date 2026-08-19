<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Base extends Model
{
    use Concerns\BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'code', 'location', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function fleets(): BelongsToMany
    {
        return $this->belongsToMany(Fleet::class, 'fleet_base');
    }

    public function units(): HasMany
    {
        return $this->hasMany(FleetUnit::class);
    }
}
