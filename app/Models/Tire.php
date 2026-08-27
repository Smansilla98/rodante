<?php

namespace App\Models;

use App\Enums\TireCondition;
use App\Enums\TireStatus;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\TireFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tire extends Model
{
    /** @use HasFactory<TireFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'public_token', 'individual_number', 'tire_brand_id', 'tire_model_id', 'tire_size_id',
        'tire_purchase_item_id', 'current_lifecycle_id', 'status', 'condition',
        'accumulated_km', 'current_tread_min', 'purchased_at', 'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TireStatus::class,
            'condition' => TireCondition::class,
            'purchased_at' => 'date',
            'retired_at' => 'date',
            'current_tread_min' => 'decimal:1',
        ];
    }

    public function displayName(): string
    {
        $code = $this->model?->code ?? 'S/M';

        return $code.' Nº'.$this->individual_number;
    }

    public function auditLabel(): string
    {
        $this->loadMissing('model');
        $label = 'Nº '.$this->individual_number;

        return $this->model?->code ? $label.' ('.$this->model->code.')' : $label;
    }

    public function treadTone(): string
    {
        if ($this->current_tread_min === null) {
            return 'unknown';
        }

        $mm = (float) $this->current_tread_min;
        if ($mm <= 4) {
            return 'critical';
        }
        if ($mm <= 8) {
            return 'warn';
        }

        return 'ok';
    }

    public function fullName(): string
    {
        $brand = $this->brand?->name ?? '';

        return trim($brand.' '.$this->displayName());
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

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(TirePurchaseItem::class, 'tire_purchase_item_id');
    }

    public function currentLifecycle(): BelongsTo
    {
        return $this->belongsTo(TireLifecycle::class, 'current_lifecycle_id');
    }

    public function ensureOpenLifecycle(): TireLifecycle
    {
        $current = $this->currentLifecycle;
        if ($current && $current->ended_at === null) {
            return $current;
        }

        $life = TireLifecycle::create([
            'tire_id' => $this->id,
            'life_number' => max(1, (int) $this->lifecycles()->max('life_number') + 1),
            'started_by' => 'COMPRA',
            'started_at' => now(),
            'condition_at_start' => $this->condition?->value ?? TireCondition::Nueva->value,
        ]);
        $this->update(['current_lifecycle_id' => $life->id]);

        return $life;
    }

    public function lifecycles(): HasMany
    {
        return $this->hasMany(TireLifecycle::class);
    }

    public function currentLocation(): HasOne
    {
        return $this->hasOne(TireCurrentLocation::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(TireMovement::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(TireIncident::class)->orderByDesc('occurred_at');
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(TireMeasurement::class)->orderByDesc('measured_at');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TireAssignment::class);
    }

    public function openAssignment(): HasOne
    {
        return $this->hasOne(TireAssignment::class)->whereNull('ended_at');
    }

    public function numberChanges(): HasMany
    {
        return $this->hasMany(TireNumberChange::class)->orderByDesc('id');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function costEntries(): HasMany
    {
        return $this->hasMany(CostEntry::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(TirePhoto::class)->orderBy('captured_at');
    }

    public function scopeInstallable(Builder $query): Builder
    {
        return $query->where('status', TireStatus::Stock);
    }
}
