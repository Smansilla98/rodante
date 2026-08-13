<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovementReason extends Model
{
    protected $fillable = ['code', 'name', 'applies_to', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
