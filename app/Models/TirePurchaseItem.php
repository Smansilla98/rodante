<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TirePurchaseItem extends Model
{
    protected $fillable = [
        'tire_purchase_id', 'tire_brand_id', 'tire_model_id', 'tire_size_id',
        'quantity', 'first_number', 'last_number', 'unit_cost', 'dot',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(TirePurchase::class, 'tire_purchase_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(TireBrand::class, 'tire_brand_id');
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(TireModel::class, 'tire_model_id');
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(TireSize::class, 'tire_size_id');
    }

    public function tires(): HasMany
    {
        return $this->hasMany(Tire::class);
    }
}
