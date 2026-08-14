<?php

namespace App\Models;

use App\Enums\UnitStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class FleetUnit extends Model
{
    protected $fillable = [
        'fleet_id', 'base_id', 'unit_type_id', 'unit_configuration_id',
        'plate', 'brand', 'model_name', 'current_odometer', 'status', 'notes', 'specs',
    ];

    protected function casts(): array
    {
        return [
            'status' => UnitStatus::class,
            'current_odometer' => 'integer',
            'specs' => 'array',
        ];
    }

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class);
    }

    public function base(): BelongsTo
    {
        return $this->belongsTo(Base::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(UnitType::class, 'unit_type_id');
    }

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(UnitConfiguration::class, 'unit_configuration_id');
    }

    public function currentCouplingAsTrailer(): HasOne
    {
        return $this->hasOne(UnitCoupling::class, 'trailer_id')->whereNull('uncoupled_at');
    }

    public function currentCouplingAsTractor(): HasOne
    {
        return $this->hasOne(UnitCoupling::class, 'tractor_id')->whereNull('uncoupled_at');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(TireCurrentLocation::class, 'unit_id');
    }

    public function hasOdometer(): bool
    {
        return (bool) $this->type?->has_odometer;
    }

    public function specSummary(): ?string
    {
        $specs = $this->specs ?? [];
        $parts = [];
        if (! empty($specs['capacity_l'])) {
            $parts[] = number_format((int) $specs['capacity_l']).' L';
        }
        if (! empty($specs['compartments'])) {
            $parts[] = $specs['compartments'].' comp.';
        }
        if (! empty($specs['material'])) {
            $parts[] = $specs['material'];
        }
        if (! empty($specs['product'])) {
            $parts[] = $specs['product'];
        }
        if (! empty($specs['suspension'])) {
            $parts[] = 'susp. '.$specs['suspension'];
        }
        if (! empty($specs['tire_width'])) {
            $parts[] = 'lineal '.(int) $specs['tire_width'];
        }

        return $parts ? implode(' · ', $parts) : null;
    }

    public function allowedTireWidth(): ?int
    {
        if ($this->hasOdometer()) {
            return null;
        }

        $width = (int) ($this->specs['tire_width'] ?? 0);

        return in_array($width, [295, 385], true) ? $width : null;
    }

    public function coupledPartner(): ?self
    {
        return $this->currentCouplingAsTractor?->trailer
            ?? $this->currentCouplingAsTrailer?->tractor;
    }

    public function sheetUnits(): Collection
    {
        $tractor = $this->hasOdometer() ? $this : $this->currentCouplingAsTrailer?->tractor;
        $trailer = $this->hasOdometer() ? $this->currentCouplingAsTractor?->trailer : $this;

        return collect([$tractor, $trailer])->filter()->unique('id')->values();
    }

    public function tireLayout(): Collection
    {
        $this->loadMissing([
            'configuration.positions',
            'locations.tire.brand',
            'locations.tire.model',
            'locations.tire.size',
            'locations.tire.openAssignment',
            'locations.position',
        ]);

        return $this->configuration->positions->map(fn ($position) => [
            'position' => $position,
            'tire' => $this->locations->firstWhere('position_id', $position->id)?->tire,
        ]);
    }
}
