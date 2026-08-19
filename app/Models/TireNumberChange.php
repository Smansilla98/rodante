<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TireNumberChange extends Model
{
    protected $fillable = ['tire_id', 'from_number', 'to_number', 'user_id', 'reason'];

    public function tire(): BelongsTo
    {
        return $this->belongsTo(Tire::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
