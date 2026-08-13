<?php

namespace App\Services;

use App\Enums\OdometerStatus;
use App\Enums\UserRole;
use App\Exceptions\DomainException;
use App\Models\FleetUnit;
use App\Models\OdometerReading;
use App\Models\User;

class OdometerService
{
    public function lastValidatedValue(FleetUnit $unit): ?int
    {
        return OdometerReading::query()
            ->where('unit_id', $unit->id)
            ->where('status', OdometerStatus::Validated)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->value('value');
    }

    public function lastRecordedValue(FleetUnit $unit): ?int
    {
        return OdometerReading::query()
            ->where('unit_id', $unit->id)
            ->where('status', '!=', OdometerStatus::Rejected)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->value('value');
    }

    public function assertNotDecreasing(FleetUnit $unit, int $odometer): void
    {
        $last = $this->lastRecordedValue($unit);
        if ($last !== null && $odometer < $last) {
            throw new DomainException("El odómetro no puede ser menor a la última lectura ({$last} km).");
        }
    }

    public function record(
        FleetUnit $unit,
        int $odometer,
        User $user,
        ?int $operationId = null,
        ?string $notes = null,
    ): OdometerReading {
        if (! $unit->hasOdometer()) {
            throw new DomainException('Solo las unidades tractor registran odómetro propio.');
        }

        $this->assertNotDecreasing($unit, $odometer);

        $reading = OdometerReading::create([
            'unit_id' => $unit->id,
            'value' => $odometer,
            'status' => OdometerStatus::Pending,
            'recorded_by' => $user->id,
            'recorded_at' => now(),
            'tire_operation_id' => $operationId,
            'notes' => $notes,
        ]);

        $unit->update(['current_odometer' => $odometer]);

        return $reading;
    }

    public function validate(OdometerReading $reading, User $user): OdometerReading
    {
        if (! $user->role->canValidateOdometer()) {
            throw new DomainException('No tiene permiso para validar odómetros.');
        }
        if ($reading->status !== OdometerStatus::Pending) {
            throw new DomainException('La lectura ya no está pendiente.');
        }

        $source = $user->role === UserRole::Logistica ? 'LOGISTICA' : 'JEFE_SECTOR';
        if ($user->role === UserRole::Administrador) {
            $source = 'JEFE_SECTOR';
        }

        $reading->update([
            'status' => OdometerStatus::Validated,
            'validated_by' => $user->id,
            'validation_source' => $source,
            'validated_at' => now(),
        ]);

        return $reading->refresh();
    }

    public function reject(OdometerReading $reading, User $user, string $notes): OdometerReading
    {
        if (! $user->role->canValidateOdometer()) {
            throw new DomainException('No tiene permiso para rechazar odómetros.');
        }
        if ($reading->status !== OdometerStatus::Pending) {
            throw new DomainException('La lectura ya no está pendiente.');
        }

        $reading->update([
            'status' => OdometerStatus::Rejected,
            'validated_by' => $user->id,
            'validation_source' => $user->role === UserRole::Logistica ? 'LOGISTICA' : 'JEFE_SECTOR',
            'validated_at' => now(),
            'notes' => trim(($reading->notes ? $reading->notes.' | ' : '').'Rechazo: '.$notes),
        ]);

        return $reading->refresh();
    }
}
