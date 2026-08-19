<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TirePurchase extends Model
{
    use Concerns\BelongsToCompany;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_CONFIRMED = 'CONFIRMED';

    protected $fillable = [
        'company_id', 'number', 'supplier_id', 'base_id', 'user_id',
        'purchased_at', 'status', 'notes', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'purchased_at' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function base(): BelongsTo
    {
        return $this->belongsTo(Base::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TirePurchaseItem::class);
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }
}
