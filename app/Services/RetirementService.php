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
        if ($tire->openAssignment) {
            throw new DomainException('Retirá el neumático de la unidad antes de darlo de baja.');
        }

        $files = $data['photos'] ?? [];
        unset($data['photos']);

        $retired = DB::transaction(function () use ($tire, $data, $user, $files) {
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
}
