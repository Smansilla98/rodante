<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Exceptions\DomainException;
use App\Models\Tire;
use App\Models\TireMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MovementCorrectionService
{
    public function __construct(private AuditService $audit) {}

    public function record(Tire $tire, string $notes, User $user): TireMovement
    {
        if (! $user->role->canManageAbm()) {
            throw new DomainException('Solo un administrador puede asentar una corrección de historial.');
        }
        $notes = trim($notes);
        if ($notes === '') {
            throw new DomainException('La corrección tiene que explicar qué se aclara. El movimiento original no se toca.');
        }

        return DB::transaction(function () use ($tire, $notes, $user) {
            $movement = $tire->movements()->create([
                'type' => MovementType::Correction,
                'occurred_at' => now(),
                'user_id' => $user->id,
                'notes' => $notes,
                'created_at' => now(),
            ]);
            $this->audit->log('movement.correction', $tire, null, ['notes' => $notes, 'movement_id' => $movement->id]);

            return $movement;
        });
    }
}
