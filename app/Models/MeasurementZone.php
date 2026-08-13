<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeasurementZone extends Model
{
    public $timestamps = false;

    protected $fillable = ['tire_size_id', 'code', 'name', 'sort_order'];

    public function size(): BelongsTo
    {
        return $this->belongsTo(TireSize::class, 'tire_size_id');
    }
}
