<?php

namespace App\Models;

use App\Enums\InventorySessionStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventorySession extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'base_id', 'number', 'status', 'expected_count', 'found_count',
        'missing_count', 'unexpected_count', 'notes', 'opened_by', 'closed_by', 'approved_by',
        'opened_at', 'counting_started_at', 'submitted_at', 'closed_at', 'cancelled_at',
        'adjustments_applied',
    ];

    protected function casts(): array
    {
        return [
            'status' => InventorySessionStatus::class,
            'opened_at' => 'datetime',
            'counting_started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'adjustments_applied' => 'boolean',
        ];
    }

    public function base(): BelongsTo
    {
        return $this->belongsTo(Base::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryLine::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
