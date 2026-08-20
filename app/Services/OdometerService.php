<?php

namespace App\Services;

use App\Enums\OdometerStatus;
use App\Exceptions\DomainException;
use App\Models\FleetUnit;
use App\Models\OdometerReading;
use App\Models\Tire;
use App\Models\TireAssignmentSegment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OdometerService
{
    public function __construct(
        private AuditService $audit,
    ) {}

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

        $last = OdometerReading::query()
            ->where('unit_id', $unit->id)
            ->where('status', '!=', OdometerStatus::Rejected)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first();

        if ($last && (int) $last->value === $odometer) {
            $unit->update(['current_odometer' => $odometer]);

            return $last;
        }

        $reading = OdometerReading::create([
            'unit_id' => $unit->id,
            'value' => $odometer,
            'status' => OdometerStatus::Validated,
            'recorded_by' => $user->id,
            'recorded_at' => now(),
            'validated_by' => $user->id,
            'validated_at' => now(),
            'validation_source' => 'OPERACION',
            'tire_operation_id' => $operationId,
            'notes' => $notes,
        ]);

        $unit->update(['current_odometer' => $odometer]);

        return $reading;
    }

    public function update(OdometerReading $reading, int $value, User $user, ?string $notes = null): OdometerReading
    {
        if (! $user->role->canValidateOdometer()) {
            throw new DomainException('No tiene permiso para corregir odómetros.');
        }
        if ($reading->status === OdometerStatus::Rejected) {
            throw new DomainException('Una lectura rechazada no se edita: cargá una nueva.');
        }

        $previous = $this->neighborValue($reading, 'previous');
        $next = $this->neighborValue($reading, 'next');
        if ($previous !== null && $value < $previous) {
            throw new DomainException("No puede ser menor a la lectura anterior ({$previous} km).");
        }
        if ($next !== null && $value > $next) {
            throw new DomainException("No puede ser mayor a la lectura siguiente ({$next} km).");
        }

        return DB::transaction(function () use ($reading, $value, $user, $notes) {
            $old = $reading->value;
            $reading->update([
                'value' => $value,
                'status' => OdometerStatus::Validated,
                'validated_by' => $user->id,
                'validated_at' => now(),
                'notes' => $notes ?? $reading->notes,
            ]);

            $unit = $reading->unit()->lockForUpdate()->first();
            $this->refreshCurrentOdometer($unit);
            if ($unit && (int) $old !== (int) $value) {
                $this->realignSegmentsForCorrection($unit, (int) $old, (int) $value);
            }
            $this->audit->log('odometer.updated', $reading->fresh(['unit']), ['value' => $old], [
                'unit' => $reading->unit?->plate,
                'odometer' => $value,
            ]);

            return $reading->fresh();
        });
    }

    /**
     * Recalcula tramos que usaron el valor corregido como inicio o cierre.
     * Los movimientos históricos no se reescriben (inmutables); accumulated_km sale de segmentos.
     */
    private function realignSegmentsForCorrection(FleetUnit $unit, int $oldValue, int $newValue): void
    {
        $segments = TireAssignmentSegment::query()
            ->where('odometer_unit_id', $unit->id)
            ->where(function ($q) use ($oldValue) {
                $q->where('start_odometer', $oldValue)
                    ->orWhere('end_odometer', $oldValue);
            })
            ->with('assignment')
            ->lockForUpdate()
            ->get();

        $tireIds = [];
        foreach ($segments as $segment) {
            $start = (int) $segment->start_odometer === $oldValue ? $newValue : (int) $segment->start_odometer;
            $end = $segment->end_odometer !== null
                ? ((int) $segment->end_odometer === $oldValue ? $newValue : (int) $segment->end_odometer)
                : null;

            if ($end !== null && $end < $start) {
                throw new DomainException(
                    'La corrección de odómetro dejaría un tramo con cierre menor al inicio ('.$end.' < '.$start.').'
                );
            }

            $km = 0;
            if ($segment->counts_km && $end !== null) {
                $km = $end - $start;
            }

            $segment->update([
                'start_odometer' => $start,
                'end_odometer' => $end,
                'km_delta' => $km,
            ]);

            if ($segment->assignment?->tire_id) {
                $tireIds[] = (int) $segment->assignment->tire_id;
            }
        }

        $locations = app(LocationService::class);
        foreach (array_unique($tireIds) as $tireId) {
            $tire = Tire::query()->find($tireId);
            if ($tire) {
                $locations->refreshAccumulatedKm($tire);
            }
        }
    }

    private function neighborValue(OdometerReading $reading, string $direction): ?int
    {
        $query = OdometerReading::query()
            ->where('unit_id', $reading->unit_id)
            ->where('id', '!=', $reading->id)
            ->where('status', '!=', OdometerStatus::Rejected);

        if ($direction === 'previous') {
            $query->where(function ($inner) use ($reading) {
                $inner->where('recorded_at', '<', $reading->recorded_at)
                    ->orWhere(function ($same) use ($reading) {
                        $same->where('recorded_at', $reading->recorded_at)->where('id', '<', $reading->id);
                    });
            })->orderByDesc('recorded_at')->orderByDesc('id');
        } else {
            $query->where(function ($inner) use ($reading) {
                $inner->where('recorded_at', '>', $reading->recorded_at)
                    ->orWhere(function ($same) use ($reading) {
                        $same->where('recorded_at', $reading->recorded_at)->where('id', '>', $reading->id);
                    });
            })->orderBy('recorded_at')->orderBy('id');
        }

        return $query->value('value');
    }

    private function refreshCurrentOdometer(FleetUnit $unit): void
    {
        $unit->update(['current_odometer' => $this->lastRecordedValue($unit) ?? 0]);
    }
}
