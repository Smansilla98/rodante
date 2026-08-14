<?php

namespace App\Services;

use App\Enums\IncidentType;
use App\Enums\LocationKind;
use App\Enums\TireCondition;
use App\Enums\TireStatus;
use App\Exceptions\DomainException;
use App\Models\Tire;
use App\Models\TireIncident;
use App\Models\TireLifecycle;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class IncidentService
{
    public function __construct(
        private LocationService $locations,
        private AuditService $audit,
    ) {}

    public function register(Tire $tire, array $data, User $user): TireIncident
    {
        if ($tire->status === TireStatus::DeBaja) {
            throw new DomainException('No se pueden cargar incidencias sobre un neumático de baja.');
        }

        return DB::transaction(function () use ($tire, $data, $user) {
            $type = IncidentType::from($data['type']);

            $incident = TireIncident::create([
                'tire_id' => $tire->id,
                'type' => $type,
                'occurred_at' => $data['occurred_at'] ?? now(),
                'unit_id' => $data['unit_id'] ?? $tire->currentLocation?->unit_id,
                'position_id' => $data['position_id'] ?? $tire->currentLocation?->position_id,
                'odometer' => $data['odometer'] ?? null,
                'user_id' => $user->id,
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($type === IncidentType::Reparacion) {
                $tire->update(['condition' => TireCondition::Reparada]);
            }

            if ($type === IncidentType::Recapado) {
                $this->openNewLife($tire, $user);
            }

            $incident->load('unit');
            $this->audit->log('tire.incident', $incident, null, [
                'type' => $type->value,
                'tire' => $tire->auditLabel(),
                'unit' => $incident->unit?->plate,
            ]);

            return $incident;
        });
    }

    private function openNewLife(Tire $tire, User $user): void
    {
        if (! $user->role->canRetireOrRecap()) {
            throw new DomainException('Solo el jefe de sector o un administrador pueden registrar un recapado.');
        }
        if ($tire->openAssignment) {
            throw new DomainException('Retirá el neumático de la unidad antes de recaparlo.');
        }

        $current = $tire->currentLifecycle;
        if ($current && $current->ended_at === null) {
            $current->update(['ended_at' => now()]);
        }

        $life = TireLifecycle::create([
            'tire_id' => $tire->id,
            'life_number' => ((int) $tire->lifecycles()->max('life_number')) + 1,
            'started_by' => 'RECAPADO',
            'started_at' => now(),
            'condition_at_start' => TireCondition::Recapada->value,
        ]);

        $tire->update([
            'current_lifecycle_id' => $life->id,
            'condition' => TireCondition::Recapada,
        ]);

        $baseId = $tire->currentLocation?->base_id;
        $this->locations->place($tire, LocationKind::Stock, $baseId);
    }
}
