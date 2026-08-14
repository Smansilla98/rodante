<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitConfiguration extends Model
{
    protected $fillable = [
        'code', 'name', 'family_code', 'applies_to', 'compatible_types',
        'description', 'axle_count', 'drive_axle_count', 'position_count', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'compatible_types' => 'array',
        ];
    }

    public function positions(): HasMany
    {
        return $this->hasMany(UnitPosition::class)->orderBy('sort_order');
    }

    public function isCompatibleWith(UnitType $type): bool
    {
        $types = $this->compatible_types ?? [];

        return in_array($type->code, $types, true);
    }

    public function scopeForType(Builder $query, UnitType $type): Builder
    {
        return $query->where(function (Builder $inner) use ($type) {
            $inner->whereJsonContains('compatible_types', $type->code)
                ->orWhereNull('compatible_types');
        });
    }

    public function label(): string
    {
        return $this->code.' — '.$this->name;
    }
}
