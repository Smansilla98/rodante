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
        private PositionFitService $fit,
    ) {}

    /**
     * @param  array{odometer:int,occurred_at?:string,notes?:string,removals?:array,installations?:array}  $data
     */
    public function execute(FleetUnit $unit, array $data, User $user): TireOperation
    {
        if (! $user->role->canWrite()) {
            throw new DomainException('No tiene permiso para operar cubiertas.');
        }

        return DB::transaction(function () use ($unit, $data, $user) {
            $unit = FleetUnit::with('type', 'configuration.positions')->lockForUpdate()->findOrFail($unit->id);
            $odometerUnit = $this->couplings->resolveOdometerUnit($unit);
            $odometerUnit = FleetUnit::lockForUpdate()->findOrFail($odometerUnit->id);
            $odometer = (int) $data['odometer'];
            $occurredAt = $data['occurred_at'] ?? now();

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

    public function rotate(FleetUnit $unit, int $tireId, int $toPositionId, int $odometer, User $user, ?string $notes = null): TireAssignment
    {
        $this->relocateMounted($unit, [$tireId => $toPositionId], $odometer, $user, $notes);

        return TireAssignment::where('tire_id', $tireId)->whereNull('ended_at')->firstOrFail();
    }

    /**
     * Aplica un esquema de rotación (pares de ubicaciones) sin cerrar km.
     *
     * @param  list<array{0:int,1:int}>  $pairs
     */
    public function applyPattern(FleetUnit $unit, array $pairs, int $odometer, User $user, ?string $notes = null): void
    {
        $moves = [];
        foreach ($pairs as [$fromId, $toId]) {
            $from = TireCurrentLocation::where('unit_id', $unit->id)->where('position_id', $fromId)->first();
            $to = TireCurrentLocation::where('unit_id', $unit->id)->where('position_id', $toId)->first();
            if (! $from?->tire_id || ! $to?->tire_id) {
                throw new DomainException('El esquema necesita cubiertas en todas las ubicaciones.');
            }
            $moves[$from->tire_id] = $toId;
            $moves[$to->tire_id] = $fromId;
        }

        $this->relocateMounted($unit, $moves, $odometer, $user, $notes);
    }

    /**
     * Mueve cubiertas ya montadas entre ubicaciones de la misma unidad.
     * Si el destino está ocupado, intercambia. Los kilómetros del periodo siguen abiertos.
     *
     * @param  array<int, int>  $tireToPosition  tire_id => position_id destino
     */
    public function relocateMounted(FleetUnit $unit, array $tireToPosition, int $odometer, User $user, ?string $notes = null): void
    {
        DB::transaction(function () use ($unit, $tireToPosition, $odometer, $user, $notes) {
            $unit = FleetUnit::lockForUpdate()->findOrFail($unit->id);
            $odometerUnit = $this->couplings->resolveOdometerUnit($unit);

            $tireIds = array_map('intval', array_keys($tireToPosition));
            Tire::whereIn('id', $tireIds)->lockForUpdate()->get();
            $locations = TireCurrentLocation::where('unit_id', $unit->id)->lockForUpdate()->get()->keyBy('tire_id');

            $resolved = [];
            foreach ($tireToPosition as $tireId => $toPositionId) {
                $tireId = (int) $tireId;
                $toPositionId = (int) $toPositionId;
                $location = $locations->get($tireId);
                if (! $location || $location->unit_id !== $unit->id) {
                    throw new DomainException('El neumático no está instalado en esta unidad.');
                }

                $fromPositionId = (int) $location->position_id;
                if ($fromPositionId === $toPositionId) {
                    continue;
                }

                $toPosition = UnitPosition::where('unit_configuration_id', $unit->unit_configuration_id)
                    ->where('id', $toPositionId)
                    ->firstOrFail();

                $tire = Tire::findOrFail($tireId);
                $this->fit->assertCanMount($tire, $toPosition, $unit);

                $assignment = TireAssignment::where('tire_id', $tireId)->whereNull('ended_at')->lockForUpdate()->first();
                if (! $assignment) {
                    throw new DomainException('No hay un periodo de uso abierto para rotar.');
                }

                $occupant = $locations->first(fn ($row) => (int) $row->position_id === $toPositionId && (int) $row->tire_id !== $tireId);
                if ($occupant && ! array_key_exists($occupant->tire_id, $tireToPosition)) {
                    $other = Tire::findOrFail($occupant->tire_id);
                    $fromPosition = UnitPosition::findOrFail($fromPositionId);
                    $this->fit->assertCanMount($other, $fromPosition, $unit);
                    $resolved[$occupant->tire_id] = [
                        'from' => (int) $occupant->position_id,
                        'to' => $fromPositionId,
                        'position' => $fromPosition,
                    ];
                }

                $resolved[$tireId] = [
                    'from' => $fromPositionId,
                    'to' => $toPositionId,
                    'position' => $toPosition,
                ];
            }

            if ($resolved === []) {
                throw new DomainException('No hay cubiertas para mover.');
            }

            $locationIds = TireCurrentLocation::whereIn('tire_id', array_keys($resolved))->pluck('id');
            TireCurrentLocation::whereIn('id', $locationIds)->update(['position_id' => null]);

            $occurredAt = now();
            foreach ($resolved as $tireId => $move) {
                $tire = Tire::findOrFail($tireId);
                $kind = $move['position']->is_spare ? LocationKind::Auxilio : LocationKind::Instalada;
                $this->locations->place($tire, $kind, $unit->base_id, $unit->id, $move['to']);
                $countsKm = ! $move['position']->is_spare;
                $assignment = TireAssignment::where('tire_id', $tireId)->whereNull('ended_at')->first();
                if ($assignment) {
                    $segment = $assignment->openSegment;
                    if ($segment && (bool) $segment->counts_km !== $countsKm) {
                        $this->couplings->closeSegment($segment, $odometer);
                        TireAssignmentSegment::create([
                            'tire_assignment_id' => $assignment->id,
                            'odometer_unit_id' => $odometerUnit->id,
                            'start_odometer' => $odometer,
                            'counts_km' => $countsKm,
                            'started_at' => $occurredAt,
                            'open_key' => $assignment->id,
                        ]);
                        $this->locations->refreshAccumulatedKm($tire->fresh());
                    }
                    $assignment->update(['counts_km' => $countsKm]);
                }
                $tire->movements()->create([
                    'type' => MovementType::Rotate,
                    'occurred_at' => $occurredAt,
                    'from_unit_id' => $unit->id,
                    'from_position_id' => $move['from'],
                    'from_odometer' => $odometer,
                    'to_unit_id' => $unit->id,
                    'to_position_id' => $move['to'],
                    'to_odometer' => $odometer,
                    'km_delta' => 0,
                    'counts_km' => false,
                    'user_id' => $user->id,
                    'notes' => $notes,
                    'created_at' => now(),
                ]);
            }

            $this->odometers->record($odometerUnit, $odometer, $user);
            $this->audit->log('tire.rotated', $unit, null, [
                'moves' => count($resolved),
                'unit' => $unit->plate,
            ]);
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

        $this->fit->assertCanMount($tire, $position, $unit);

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

    public function returnToStock(Tire $tire, User $user, ?string $notes = null): Tire
    {
        if (! $user->role->canWrite()) {
            throw new DomainException('No tiene permiso para devolver cubiertas a stock.');
        }

        $status = $tire->status;
        if (! in_array($status, [TireStatus::EnReparacion, TireStatus::Reserva], true)) {
            throw new DomainException('Solo se puede devolver a stock una cubierta en reparación o reserva.');
        }
        if ($tire->openAssignment) {
            throw new DomainException('Retirá la cubierta de la unidad antes de devolverla a stock.');
        }

        return DB::transaction(function () use ($tire, $user, $notes, $status) {
            $tire = Tire::lockForUpdate()->findOrFail($tire->id);
            $baseId = $tire->currentLocation?->base_id;
            $type = $status === TireStatus::EnReparacion ? MovementType::FromRepair : MovementType::FromReserva;

            $this->locations->place($tire, LocationKind::Stock, $baseId);

            if ($status === TireStatus::EnReparacion && $tire->condition !== TireCondition::Recapada) {
                $tire->update(['condition' => TireCondition::Reparada]);
            }

            $tire->movements()->create([
                'type' => $type,
                'occurred_at' => now(),
                'from_base_id' => $baseId,
                'to_base_id' => $baseId,
                'user_id' => $user->id,
                'notes' => $notes ?? ($status === TireStatus::EnReparacion
                    ? 'Vuelta a stock después de reparación (parche). Misma vida.'
                    : 'Salida de reserva a stock.'),
                'created_at' => now(),
            ]);

            $this->audit->log('tire.returned_stock', $tire, null, [
                'from' => $status->value,
                'tire' => $tire->auditLabel(),
            ]);

            return $tire->fresh();
        });
    }
}
