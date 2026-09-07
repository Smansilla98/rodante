<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TireLifecycle extends Model
{

    use BelongsToCompany;
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'tire_id', 'life_number', 'started_by', 'started_at',
        'ended_at', 'km_in_life', 'condition_at_start',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function tire(): BelongsTo
    {
        return $this->belongsTo(Tire::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TireAssignment::class);
    }
}
