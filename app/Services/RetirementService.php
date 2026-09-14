<?php

namespace App\Services;

use App\Enums\LocationKind;
use App\Enums\MovementType;
use App\Enums\TireStatus;
use App\Exceptions\DomainException;
use App\Models\Tire;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RetirementService
{
    public function __construct(
        private LocationService $locations,
        private AuditService $audit,
        private TirePhotoService $photos,
        private TelemetryService $telemetry,
    ) {}

    public function retire(Tire $tire, array $data, User $user): Tire
    {
        if (! $user->role->canRetireOrRecap()) {
            throw new DomainException('Solo el jefe de sector o un administrador pueden dar de baja.');
        }
        if ($tire->status === TireStatus::DeBaja) {
            throw new DomainException('El neumático ya está de baja.');
        }

        $this->assertNotMountedOnUnit($tire);

        $files = $data['photos'] ?? [];
        unset($data['photos']);

        $retired = DB::transaction(function () use ($tire, $data, $user, $files) {
            $tire->refresh()->load(['openAssignment', 'currentLocation']);
            $this->assertNotMountedOnUnit($tire);

            $location = $tire->currentLocation;
            $this->locations->refreshAccumulatedKm($tire);

            $tire->movements()->create([
                'type' => MovementType::Retire,
                'occurred_at' => $data['occurred_at'] ?? now(),
                'from_base_id' => $location?->base_id,
                'from_unit_id' => $location?->unit_id,
                'from_position_id' => $location?->position_id,
                'reason_id' => $data['reason_id'] ?? null,
                'user_id' => $user->id,
                'notes' => $data['notes'] ?? null,
                'created_at' => now(),
            ]);

            $life = $tire->currentLifecycle;
            if ($life && $life->ended_at === null) {
                $life->update(['ended_at' => now()]);
            }

            $this->locations->place($tire, LocationKind::DeBaja, $location?->base_id);
            $tire->update(['retired_at' => now()->toDateString()]);

            $this->photos->storeRetirement($tire, is_array($files) ? $files : [], $user);

            $this->audit->log('tire.retired', $tire, null, [
                'reason_id' => $data['reason_id'] ?? null,
                'km' => $tire->accumulated_km,
                'tire' => $tire->auditLabel(),
                'photos' => $tire->photos()->where('kind', TirePhotoService::KIND_RETIRE)->count(),
            ]);

            return $tire->fresh();
        });

        $this->telemetry->record('tire.retired', $retired, [
            'tire' => $retired->auditLabel(),
            'km' => $retired->accumulated_km,
            'photos' => $retired->photos()->where('kind', TirePhotoService::KIND_RETIRE)->count(),
        ]);

        return $retired;
    }

    /**
     * La baja solo se permite si la cubierta no está colocada en una unidad (ni rodaje ni auxilio).
     */
    public function assertNotMountedOnUnit(Tire $tire): void
    {
        $tire->loadMissing(['openAssignment.unit', 'currentLocation.unit', 'currentLocation.position']);

        if ($tire->openAssignment) {
            $plate = $tire->openAssignment->unit?->plate ?? 'una unidad';
            throw new DomainException(
                "Retirá la cubierta de la unidad {$plate} (planilla) antes de darla de baja."
            );
        }

        if ($tire->currentLocation?->unit_id) {
            $plate = $tire->currentLocation->unit?->plate ?? 'una unidad';
            $pos = $tire->currentLocation->position?->name;
            throw new DomainException(
                'La cubierta sigue colocada en '.$plate.($pos ? ' · '.$pos : '').'. Retirala a stock desde la planilla antes de darla de baja.'
            );
        }

        if (in_array($tire->status, [TireStatus::Instalada, TireStatus::Auxilio], true)) {
            throw new DomainException(
                'Esta cubierta figura como '.$tire->status->label().'. Retirala de la unidad antes de darla de baja.'
            );
        }
    }

    public function isEligibleForRetirement(Tire $tire): bool
    {
        try {
            if ($tire->status === TireStatus::DeBaja) {
                return false;
            }
            $this->assertNotMountedOnUnit($tire);

            return true;
        } catch (DomainException) {
            return false;
        }
    }
}
