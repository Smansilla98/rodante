<?php

namespace App\Services;

use App\Enums\LocationKind;
use App\Enums\MovementType;
use App\Enums\TireCondition;
use App\Enums\TireStatus;
use App\Exceptions\DomainException;
use App\Models\FleetUnit;
use App\Models\Tire;
use App\Models\TireAssignment;
use App\Models\TireAssignmentSegment;
use App\Models\TireCurrentLocation;
use App\Models\TireOperation;
use App\Models\UnitPosition;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TireOperationService
{
    public function __construct(
        private CouplingService $couplings,
        private OdometerService $odometers,
        private LocationService $locations,
        private AuditService $audit,
    ) {}

    /**
     * @param  array{odometer:int,occurred_at?:string,notes?:string,removals?:array,installations?:array}  $data
     */
    public function execute(FleetUnit $unit, array $data, User $user): TireOperation
    {
        return DB::transaction(function () use ($unit, $data, $user) {
            $unit = FleetUnit::with('type', 'configuration.positions')->lockForUpdate()->findOrFail($unit->id);
            $odometerUnit = $this->couplings->resolveOdometerUnit($unit);
            $odometerUnit = FleetUnit::lockForUpdate()->findOrFail($odometerUnit->id);
            $odometer = (int) $data['odometer'];
            $occurredAt = $data['occurred_at'] ?? now();

            $this->odometers->assertNotDecreasing($odometerUnit, $odometer);

            $removals = $data['removals'] ?? [];
            $installations = $data['installations'] ?? [];
            if ($removals === [] && $installations === []) {
                throw new DomainException('La operación debe retirar o instalar al menos un neumático.');
            }

            $tireIds = collect($removals)->pluck('tire_id')
                ->merge(collect($installations)->pluck('tire_id'))
                ->unique()
                ->values();

            Tire::whereIn('id', $tireIds)->lockForUpdate()->get();
            TireCurrentLocation::whereIn('tire_id', $tireIds)->lockForUpdate()->get();

            $operation = TireOperation::create([
                'unit_id' => $unit->id,
                'odometer_unit_id' => $odometerUnit->id,
                'user_id' => $user->id,
                'odometer' => $odometer,
                'occurred_at' => $occurredAt,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->odometers->record($odometerUnit, $odometer, $user, $operation->id);

            foreach ($removals as $removal) {
                $this->removeToStock($unit, $operation, $removal, $odometerUnit, $odometer, $occurredAt, $user);
            }

            foreach ($installations as $installation) {
                $this->installFromStock($unit, $operation, $installation, $odometerUnit, $odometer, $occurredAt, $user);
            }

            $this->audit->log('tire.operation', $operation, null, [
                'unit' => $unit->plate,
                'odometer' => $odometer,
                'removals' => count($removals),
                'installations' => count($installations),
            ]);

            return $operation->load('movements.tire');
        });
    }

    public function rotate(FleetUnit $unit, int $tireId, int $toPositionId, int $odometer, User $user): TireAssignment
    {
        return DB::transaction(function () use ($unit, $tireId, $toPositionId, $odometer, $user) {
            $unit = FleetUnit::lockForUpdate()->findOrFail($unit->id);
            $tire = Tire::lockForUpdate()->findOrFail($tireId);
            $odometerUnit = $this->couplings->resolveOdometerUnit($unit);
            $this->odometers->assertNotDecreasing($odometerUnit, $odometer);

            $location = TireCurrentLocation::where('tire_id', $tire->id)->lockForUpdate()->first();
            if (! $location || $location->unit_id !== $unit->id) {
                throw new DomainException('El neumático no está instalado en esta unidad.');
            }

            $toPosition = UnitPosition::where('unit_configuration_id', $unit->unit_configuration_id)
                ->where('id', $toPositionId)
                ->firstOrFail();

            $occupied = TireCurrentLocation::where('unit_id', $unit->id)
                ->where('position_id', $toPosition->id)
                ->lockForUpdate()
                ->first();
            if ($occupied && $occupied->tire_id !== $tire->id) {
                throw new DomainException('La posición destino ya está ocupada.');
            }

            $assignment = TireAssignment::where('tire_id', $tire->id)->whereNull('ended_at')->lockForUpdate()->first();
            if (! $assignment) {
                throw new DomainException('No hay un periodo de uso abierto para rotar.');
            }

            $fromPositionId = $location->position_id;
            $kind = $toPosition->is_spare ? LocationKind::Auxilio : LocationKind::Instalada;
            $this->locations->place($tire, $kind, $unit->base_id, $unit->id, $toPosition->id);

            $tire->movements()->create([
                'type' => MovementType::Rotate,
                'occurred_at' => now(),
                'from_unit_id' => $unit->id,
                'from_position_id' => $fromPositionId,
                'from_odometer' => $odometer,
                'to_unit_id' => $unit->id,
                'to_position_id' => $toPosition->id,
                'to_odometer' => $odometer,
                'km_delta' => 0,
                'counts_km' => false,
                'user_id' => $user->id,
                'created_at' => now(),
            ]);

            $this->odometers->record($odometerUnit, $odometer, $user);
            $this->audit->log('tire.rotated', $tire);

            return $assignment;
        });
    }

    /**
     * @param  array{tire_id:int,reason_id?:int,destination?:string,notes?:string}  $removal
     */
    private function removeToStock(
        FleetUnit $unit,
        TireOperation $operation,
        array $removal,
        FleetUnit $odometerUnit,
        int $odometer,
        mixed $occurredAt,
        User $user,
    ): void {
        $tire = Tire::lockForUpdate()->findOrFail($removal['tire_id']);
        $location = TireCurrentLocation::where('tire_id', $tire->id)->lockForUpdate()->first();

        if (! $location || $location->unit_id !== $unit->id) {
            throw new DomainException($tire->displayName().' no está instalado en '.$unit->plate.'.');
        }

        $assignment = TireAssignment::where('tire_id', $tire->id)->whereNull('ended_at')->lockForUpdate()->first();
        $km = 0;
        $countsKm = false;

        if ($assignment) {
            $segment = $assignment->openSegment;
            if ($segment) {
                $km = $this->couplings->closeSegment($segment, $odometer);
                $countsKm = $segment->counts_km;
            }
            $assignment->update([
                'end_position_id' => $location->position_id,
                'ended_at' => $occurredAt,
                'open_key' => null,
            ]);
        }

        $fromPositionId = $location->position_id;
        $fromBaseId = $location->base_id;

        $this->locations->place($tire, LocationKind::Stock, $unit->base_id);
        $this->locations->refreshAccumulatedKm($tire->fresh());

        if (in_array($tire->condition, [TireCondition::Nueva, TireCondition::NuevaUsada], true) && $km > 0) {
            $tire->update(['condition' => TireCondition::Usada]);
        }

        $tire->movements()->create([
            'tire_operation_id' => $operation->id,
            'type' => MovementType::RemoveToStock,
            'occurred_at' => $occurredAt,
            'from_unit_id' => $unit->id,
            'from_position_id' => $fromPositionId,
            'from_odometer' => $odometer,
            'to_base_id' => $unit->base_id,
            'from_base_id' => $fromBaseId,
            'km_delta' => $km,
            'counts_km' => $countsKm,
            'reason_id' => $removal['reason_id'] ?? null,
            'user_id' => $user->id,
            'notes' => $removal['notes'] ?? null,
            'created_at' => now(),
        ]);

        $destination = $removal['destination'] ?? TireStatus::Stock->value;
        if ($destination === TireStatus::Reserva->value) {
            $this->moveStockTo($tire, LocationKind::Reserva, $unit->base_id, MovementType::ToReserva, $operation, $user, $occurredAt);
        } elseif ($destination === TireStatus::EnReparacion->value) {
            $this->moveStockTo($tire, LocationKind::EnReparacion, $unit->base_id, MovementType::ToRepair, $operation, $user, $occurredAt);
        }
    }

    /**
     * @param  array{tire_id:int,position_id:int,notes?:string}  $installation
     */
    private function installFromStock(
        FleetUnit $unit,
        TireOperation $operation,
        array $installation,
        FleetUnit $odometerUnit,
        int $odometer,
        mixed $occurredAt,
        User $user,
    ): void {
        $tire = Tire::lockForUpdate()->findOrFail($installation['tire_id']);
        $position = UnitPosition::where('unit_configuration_id', $unit->unit_configuration_id)
            ->where('id', $installation['position_id'])
            ->firstOrFail();

        if ($tire->status === TireStatus::DeBaja) {
            throw new DomainException($tire->displayName().' está dado de baja y no puede instalarse.');
        }
        if (! $tire->status->isInstallable()) {
            throw new DomainException($tire->displayName().' no está en stock disponible (estado: '.$tire->status->label().').');
        }

        $occupied = TireCurrentLocation::where('unit_id', $unit->id)
            ->where('position_id', $position->id)
            ->lockForUpdate()
            ->first();
        if ($occupied) {
            throw new DomainException('La posición '.$position->name.' ya está ocupada.');
        }

        if (TireAssignment::where('tire_id', $tire->id)->whereNull('ended_at')->exists()) {
            throw new DomainException($tire->displayName().' ya tiene un periodo de uso abierto.');
        }

        $fromBase = $tire->currentLocation?->base_id;
        $countsKm = ! $position->is_spare;
        $kind = $position->is_spare ? LocationKind::Auxilio : LocationKind::Instalada;

        $assignment = TireAssignment::create([
            'tire_id' => $tire->id,
            'tire_lifecycle_id' => $tire->current_lifecycle_id,
            'unit_id' => $unit->id,
            'start_position_id' => $position->id,
            'counts_km' => $countsKm,
            'started_at' => $occurredAt,
            'open_key' => $tire->id,
        ]);

        TireAssignmentSegment::create([
            'tire_assignment_id' => $assignment->id,
            'odometer_unit_id' => $odometerUnit->id,
            'start_odometer' => $odometer,
            'counts_km' => $countsKm,
            'started_at' => $occurredAt,
            'open_key' => $assignment->id,
        ]);

        $this->locations->place($tire, $kind, $unit->base_id, $unit->id, $position->id);

        $tire->movements()->create([
            'tire_operation_id' => $operation->id,
            'type' => $position->is_spare ? MovementType::ToSpare : MovementType::Install,
            'occurred_at' => $occurredAt,
            'from_base_id' => $fromBase,
            'to_unit_id' => $unit->id,
            'to_position_id' => $position->id,
            'to_odometer' => $odometer,
            'to_base_id' => $unit->base_id,
            'km_delta' => 0,
            'counts_km' => false,
            'user_id' => $user->id,
            'notes' => $installation['notes'] ?? null,
            'created_at' => now(),
        ]);
    }

    private function moveStockTo(
        Tire $tire,
        LocationKind $kind,
        int $baseId,
        MovementType $type,
        TireOperation $operation,
        User $user,
        mixed $occurredAt,
    ): void {
        $this->locations->place($tire, $kind, $baseId);
        $tire->movements()->create([
            'tire_operation_id' => $operation->id,
            'type' => $type,
            'occurred_at' => $occurredAt,
            'from_base_id' => $baseId,
            'to_base_id' => $baseId,
            'user_id' => $user->id,
            'created_at' => now(),
        ]);
    }
}
