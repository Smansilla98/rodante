<?php

namespace App\Services;

use App\Exceptions\DomainException;
use App\Models\Tire;
use App\Models\TireNumberChange;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TireIdentityService
{
    public function __construct(private AuditService $audit) {}

    public function changeNumber(Tire $tire, int $newNumber, string $reason, User $user): Tire
    {
        if (! $user->role->canManageAbm()) {
            throw new DomainException('Solo un administrador puede cambiar el número individual.');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('Indicá el motivo del cambio de número.');
        }
        if ($newNumber < 1) {
            throw new DomainException('El número individual tiene que ser positivo.');
        }
        if ((int) $tire->individual_number === $newNumber) {
            return $tire;
        }
        if (Tire::query()->where('company_id', $tire->company_id)->where('individual_number', $newNumber)->whereKeyNot($tire->id)->exists()) {
            throw new DomainException("El número {$newNumber} ya existe en la empresa.");
        }

        return DB::transaction(function () use ($tire, $newNumber, $reason, $user) {
            $from = (int) $tire->individual_number;
            TireNumberChange::create([
                'tire_id' => $tire->id,
                'from_number' => $from,
                'to_number' => $newNumber,
                'user_id' => $user->id,
                'reason' => $reason,
            ]);
            $tire->update(['individual_number' => $newNumber]);
            $this->audit->log('tire.number_changed', $tire, ['individual_number' => $from], [
                'individual_number' => $newNumber,
                'reason' => $reason,
            ]);

            return $tire->fresh();
        });
    }
}
