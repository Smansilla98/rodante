<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = ['name', 'slug', 'tax_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public static function demo(): self
    {
        return static::query()->firstOrCreate(
            ['slug' => 'demo'],
            ['name' => 'Empresa demo', 'is_active' => true],
        );
    }
}
