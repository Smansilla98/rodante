<?php

namespace App\Services;

use App\Models\CostEntry;
use App\Models\Tire;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CostService
{
    /**
     * @param  array{unit_price?: float|null, quantity?: int, fleet_unit_id?: int|null, unit_position_id?: int|null}  $attribution
     */
    public function record(
        User $user,
        string $category,
        float $amount,
        Model $costable,
        ?Tire $tire = null,
        ?string $notes = null,
        array $attribution = [],
    ): CostEntry {
        return CostEntry::create([
            'company_id' => $user->company_id,
            'category' => $category,
            'amount' => $amount,
            'unit_price' => $attribution['unit_price'] ?? null,
            'quantity' => max(1, (int) ($attribution['quantity'] ?? 1)),
            'currency' => 'ARS',
            'costable_type' => $costable::class,
            'costable_id' => $costable->getKey(),
            'tire_id' => $tire?->id,
            'fleet_unit_id' => $attribution['fleet_unit_id'] ?? null,
            'unit_position_id' => $attribution['unit_position_id'] ?? null,
            'notes' => $notes,
            'user_id' => $user->id,
            'occurred_at' => now(),
        ]);
    }

    /**
     * Unidad/posición actuales, o la última asignación cerrada (p. ej. OT desde stock).
     *
     * @return array{fleet_unit_id: int|null, unit_position_id: int|null}
     */
    public function attributionFromTire(Tire $tire): array
    {
        $tire->loadMissing('currentLocation');
        $loc = $tire->currentLocation;
        if ($loc?->unit_id && $loc->position_id) {
            return [
                'fleet_unit_id' => (int) $loc->unit_id,
                'unit_position_id' => (int) $loc->position_id,
            ];
        }

        $last = $tire->assignments()
            ->whereNotNull('ended_at')
            ->orderByDesc('ended_at')
            ->first();

        if (! $last) {
            $last = $tire->assignments()->orderByDesc('started_at')->first();
        }

        if (! $last) {
            return ['fleet_unit_id' => null, 'unit_position_id' => null];
        }

        return [
            'fleet_unit_id' => (int) $last->unit_id,
            'unit_position_id' => (int) ($last->end_position_id ?: $last->start_position_id),
        ];
    }
}
