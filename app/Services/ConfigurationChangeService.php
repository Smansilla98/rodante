<?php

namespace App\Services;

use App\Enums\LocationKind;
use App\Enums\MovementType;
use App\Exceptions\DomainException;
use App\Models\FleetUnit;
use App\Models\TireCurrentLocation;
use App\Models\UnitConfiguration;
use App\Models\UnitConfigurationChange;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConfigurationChangeService
{
    public function __construct(
        private LocationService $locations,
        private AuditService $audit,
    ) {}

    public function change(FleetUnit $unit, int $toConfigurationId, string $reason, User $user, ?string $notes = null): UnitConfigurationChange
    {
        if (! $user->role->canChangeConfiguration()) {
            throw new DomainException('Solo el jefe de sector o un administrador pueden cambiar la configuración.');
        }

        $to = UnitConfiguration::with('positions')->findOrFail($toConfigurationId);
        if ((int) $unit->unit_configuration_id === $to->id) {
            throw new DomainException('La unidad ya tiene esa configuración.');
        }

        return DB::transaction(function () use ($unit, $to, $reason, $user, $notes) {
            $unit = FleetUnit::lockForUpdate()->findOrFail($unit->id);
            $locations = TireCurrentLocation::where('unit_id', $unit->id)->lockForUpdate()->get();

            foreach ($locations as $location) {
                $tire = $location->tire;
                $this->locations->place($tire, LocationKind::Stock, $unit->base_id);
                $assignment = $tire->openAssignment;
                if ($assignment) {
                    $assignment->update([
                        'end_position_id' => $location->position_id,
                        'ended_at' => now(),
                        'open_key' => null,
                    ]);
                    $segment = $assignment->openSegment;
                    if ($segment) {
                        $odometer = $unit->hasOdometer()
                            ? $unit->current_odometer
                            : ($unit->currentCouplingAsTrailer?->tractor?->current_odometer ?? $segment->start_odometer);
                        $segment->update([
                            'end_odometer' => $odometer,
                            'km_delta' => $segment->counts_km
                                ? max(0, $odometer - $segment->start_odometer)
                                : 0,
                            'ended_at' => now(),
                            'open_key' => null,
                        ]);
                    }
                    $this->locations->refreshAccumulatedKm($tire->fresh());
                }

                $tire->movements()->create([
                    'type' => MovementType::RemoveToStock,
                    'occurred_at' => now(),
                    'from_unit_id' => $unit->id,
                    'from_position_id' => $location->position_id,
                    'to_base_id' => $unit->base_id,
                    'user_id' => $user->id,
                    'notes' => 'Retiro automático por cambio de configuración',
                    'created_at' => now(),
                ]);
            }

            $fromId = $unit->unit_configuration_id;
            $unit->update(['unit_configuration_id' => $to->id]);

            $change = UnitConfigurationChange::create([
                'unit_id' => $unit->id,
                'from_configuration_id' => $fromId,
                'to_configuration_id' => $to->id,
                'reason' => $reason,
                'user_id' => $user->id,
                'occurred_at' => now(),
                'notes' => $notes,
            ]);

            $this->audit->log('unit.configuration_changed', $change);

            return $change;
        });
    }
}
