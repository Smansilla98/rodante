<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TireOperation extends Model
{

    use BelongsToCompany;
    protected $fillable = [
        'company_id',
        'unit_id', 'odometer_unit_id', 'user_id', 'odometer', 'odometer_provisional', 'occurred_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'odometer_provisional' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(FleetUnit::class, 'unit_id');
    }

    public function odometerUnit(): BelongsTo
    {
        return $this->belongsTo(FleetUnit::class, 'odometer_unit_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(TireMovement::class);
    }
}
