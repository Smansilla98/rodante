<?php

namespace App\Models;

use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class WorkOrder extends Model
{
    use Concerns\BelongsToCompany;

    protected $fillable = [
        'company_id', 'number', 'tire_id', 'open_tire_id', 'retread_shop_id', 'type', 'status',
        'cost', 'notes', 'opened_by', 'closed_by', 'sent_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => WorkOrderType::class,
            'status' => WorkOrderStatus::class,
            'cost' => 'decimal:2',
            'sent_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function tire(): BelongsTo
    {
        return $this->belongsTo(Tire::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(RetreadShop::class, 'retread_shop_id');
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WorkOrderItem::class);
    }

    public function tiresOnOrder(): Collection
    {
        $this->loadMissing(['items.tire.model', 'items.tire.brand', 'tire.model', 'tire.brand']);

        if ($this->items->isNotEmpty()) {
            return $this->items->map->tire->filter()->values();
        }

        return collect([$this->tire])->filter()->values();
    }

    public function tireSummary(): string
    {
        $tires = $this->tiresOnOrder();
        if ($tires->isEmpty()) {
            return '—';
        }
        if ($tires->count() === 1) {
            return $tires->first()->displayName();
        }

        $names = $tires->take(2)->map->displayName()->implode(', ');

        return $tires->count().' cubiertas · '.$names.($tires->count() > 2 ? '…' : '');
    }
}
