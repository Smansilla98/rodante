<?php

namespace App\Services;

use App\Enums\LocationKind;
use App\Enums\MovementType;
use App\Enums\TireStatus;
use App\Exceptions\DomainException;
use App\Models\Base;
use App\Models\Tire;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BaseTransferService
{
    private const TRANSFERABLE = [
        TireStatus::Stock,
        TireStatus::Reserva,
        TireStatus::EnReparacion,
    ];

    public function __construct(
        private LocationService $locations,
        private AuditService $audit,
    ) {}

    public function transfer(Tire $tire, Base $toBase, User $user, ?string $notes = null): Tire
    {
        if ((int) $tire->company_id !== (int) $user->company_id
            || (int) $toBase->company_id !== (int) $user->company_id) {
            throw new DomainException('La cubierta y la base destino deben pertenecer a tu empresa.');
        }
        if ($tire->status === TireStatus::Instalada || $tire->openAssignment) {
            throw new DomainException('No se traslada una cubierta instalada. Primero retirála a stock.');
        }
        if ($tire->status === TireStatus::DeBaja) {
            throw new DomainException('Una cubierta de baja no se traslada.');
        }
        if (! in_array($tire->status, self::TRANSFERABLE, true)) {
            throw new DomainException('Solo se traslada una cubierta en stock, reserva o reparación.');
        }

        return DB::transaction(function () use ($tire, $toBase, $user, $notes) {
            $tire = Tire::query()->whereKey($tire->id)->lockForUpdate()->firstOrFail();
            if (! in_array($tire->status, self::TRANSFERABLE, true)) {
                throw new DomainException('El estado de la cubierta cambió y ya no permite trasladarla.');
            }

            $tire->load('currentLocation');
            $fromBaseId = $tire->currentLocation?->base_id;
            if ((int) $fromBaseId === (int) $toBase->id) {
                throw new DomainException('La cubierta ya está en esa base.');
            }

            $kind = LocationKind::from($tire->status->value);
            $this->locations->place($tire, $kind, $toBase->id);
            $tire->movements()->create([
                'type' => MovementType::TransferBase,
                'occurred_at' => now(),
                'from_base_id' => $fromBaseId,
                'to_base_id' => $toBase->id,
                'user_id' => $user->id,
                'notes' => $notes ?? 'Cambio de base.',
                'created_at' => now(),
            ]);
            $this->audit->log('tire.base_transferred', $tire, [
                'base_id' => $fromBaseId,
            ], [
                'base_id' => $toBase->id,
                'notes' => $notes,
            ]);

            return $tire->fresh(['currentLocation', 'movements']);
        });
    }
}
