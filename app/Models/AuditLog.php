<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'action', 'entity_type', 'entity_id',
        'old_values', 'new_values', 'ip_address', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    public function actionLabel(): string
    {
        $new = $this->new_values ?? [];

        return match ($this->action) {
            'purchase.created' => 'Cargó una compra',
            'purchase.confirmed' => 'Confirmó una compra',
            'purchase.updated' => 'Editó una compra',
            'purchase.discarded' => 'Anuló una compra',
            'odometer.updated' => 'Corrigió el odómetro',
            'tire.operation' => $this->operationAction($new),
            'tire.rotated' => 'Rotó cubiertas',
            'unit.configuration_changed' => 'Cambió la configuración',
            'coupling.created' => 'Acopló unidades',
            'coupling.closed' => 'Desacopló unidades',
            'tire.retired' => 'Dio de baja una cubierta',
            'tire.measured' => 'Midió la banda',
            'tire.incident' => $this->incidentAction($new),
            default => 'Registró un movimiento',
        };
    }

    public function detail(): string
    {
        $new = $this->new_values ?? [];
        $entity = $this->entity;

        $text = match ($this->action) {
            'purchase.created', 'purchase.confirmed', 'purchase.updated' => $this->purchaseDetail($entity),
            'purchase.discarded' => $new['number'] ?? '—',
            'odometer.updated' => $this->joinParts([
                $new['unit'] ?? $entity?->unit?->plate,
                $this->km($new['odometer'] ?? $entity?->value),
            ]),
            'tire.operation' => $this->joinParts([
                $new['unit'] ?? $entity?->unit?->plate,
                $this->km($new['odometer'] ?? $entity?->odometer),
                $this->operationCounts($new),
            ]),
            'tire.rotated' => $this->joinParts([
                $new['unit'] ?? ($entity instanceof FleetUnit ? $entity->plate : null),
                isset($new['moves']) ? $new['moves'].' cubiertas' : null,
            ]),
            'unit.configuration_changed' => $this->configurationDetail($entity, $new),
            'coupling.created' => $this->joinParts([
                $this->couplingPhrase($new['tractor'] ?? $entity?->tractor?->plate, $new['trailer'] ?? $entity?->trailer?->plate),
                $this->km($new['odometer'] ?? $entity?->tractor_odometer_start),
            ]),
            'coupling.closed' => $this->joinParts([
                $this->couplingPhrase($new['tractor'] ?? $entity?->tractor?->plate, $new['trailer'] ?? $entity?->trailer?->plate),
                $this->km($new['odometer'] ?? $entity?->tractor_odometer_end),
            ]),
            'tire.retired' => $this->joinParts([
                $new['tire'] ?? $this->tirePhrase($entity instanceof Tire ? $entity : null),
                $this->km($new['km'] ?? $entity?->accumulated_km),
            ]),
            'tire.measured' => $this->joinParts([
                $new['tire'] ?? $this->tirePhrase($entity?->tire),
                $new['unit'] ?? $entity?->unit?->plate,
                $this->km($new['odometer'] ?? $entity?->odometer),
                isset($new['min_mm']) ? $new['min_mm'].' mm' : null,
            ]),
            'tire.incident' => $this->joinParts([
                $new['tire'] ?? $this->tirePhrase($entity?->tire),
                $new['unit'] ?? $entity?->unit?->plate,
            ]),
            default => '—',
        };

        return $text !== '' ? $text : '—';
    }

    private function operationAction(array $new): string
    {
        $out = (int) ($new['removals'] ?? 0);
        $in = (int) ($new['installations'] ?? 0);
        if ($out > 0 && $in > 0) {
            return 'Cambio de cubiertas';
        }
        if ($in > 0) {
            return 'Montó cubiertas';
        }
        if ($out > 0) {
            return 'Retiró cubiertas';
        }

        return 'Trabajo en planilla';
    }

    private function incidentAction(array $new): string
    {
        $type = $new['type'] ?? $this->entity?->type?->value ?? null;

        return match ($type) {
            'PINCHADURA' => 'Registró una pinchadura',
            'SOPLADURA' => 'Registró una sopladura',
            'PARCHE' => 'Registró un parche',
            'REPARACION' => 'Registró una reparación',
            'RECAPADO' => 'Registró un recapado',
            'INSPECCION' => 'Registró una inspección',
            'DESGASTE_IRREGULAR' => 'Registró desgaste irregular',
            'CAMBIO' => 'Registró un cambio',
            default => 'Registró una incidencia',
        };
    }

    private function operationCounts(array $new): ?string
    {
        $parts = [];
        if (! empty($new['installations'])) {
            $parts[] = $new['installations'].' montadas';
        }
        if (! empty($new['removals'])) {
            $parts[] = $new['removals'].' retiradas';
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    private function purchaseDetail(?Model $entity): string
    {
        if (! $entity instanceof TirePurchase) {
            return '—';
        }

        return $this->joinParts([
            $entity->supplier?->name,
            $entity->number ? 'Compra '.$entity->number : null,
            $entity->base?->name,
        ]);
    }

    private function configurationDetail(?Model $entity, array $new): string
    {
        $from = $new['from'] ?? ($entity instanceof UnitConfigurationChange ? $entity->fromConfiguration?->name : null);
        $to = $new['to'] ?? ($entity instanceof UnitConfigurationChange ? $entity->toConfiguration?->name : null);
        $change = ($from && $to) ? 'de '.$from.' a '.$to : ($to ?: $from);

        return $this->joinParts([
            $new['unit'] ?? ($entity instanceof UnitConfigurationChange ? $entity->unit?->plate : null),
            $change,
            $new['reason'] ?? ($entity instanceof UnitConfigurationChange ? $entity->reason : null),
        ]);
    }

    private function couplingPhrase(?string $tractor, ?string $trailer): ?string
    {
        $tractor = trim((string) $tractor);
        $trailer = trim((string) $trailer);

        if ($tractor !== '' && $trailer !== '') {
            return $tractor.' con '.$trailer;
        }

        return $tractor !== '' ? $tractor : ($trailer !== '' ? $trailer : null);
    }

    private function tirePhrase(?Tire $tire): ?string
    {
        if (! $tire) {
            return null;
        }

        return $tire->auditLabel();
    }

    private function km(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((int) $value, 0, ',', '.').' km';
    }

    private function joinParts(array $parts): string
    {
        return collect($parts)
            ->map(fn ($part) => is_string($part) ? trim($part) : $part)
            ->filter(fn ($part) => $part !== null && $part !== '' && $part !== '+' && $part !== '→')
            ->implode(' · ');
    }
}
