<?php

namespace App\Services;

use App\Enums\TireApplication;
use App\Enums\TireCondition;
use App\Exceptions\DomainException;
use App\Models\FleetUnit;
use App\Models\Tire;
use App\Models\UnitPosition;

class PositionFitService
{
    public function assertCanMount(Tire $tire, UnitPosition $position, ?FleetUnit $unit = null): void
    {
        $tire->loadMissing('model', 'currentLifecycle', 'size');

        if ($unit) {
            $this->assertSizeFitsUnit($tire, $unit);
        }

        if ($position->is_spare || $position->axle_role === 'AUXILIO') {
            return;
        }

        $application = $tire->model?->application;
        $role = $position->axle_role;
        $isSteerPosition = in_array($role, ['DIRECCION', 'DIRECCIONAL'], true);

        if ($application === TireApplication::Traccion && $isSteerPosition) {
            throw new DomainException(
                $tire->displayName().' es de tracción y no puede instalarse en dirección.'
            );
        }

        if ($application !== TireApplication::Direccion) {
            return;
        }

        if ($role !== 'TRACCION') {
            return;
        }

        if ((int) $position->axle_number === 3) {
            return;
        }

        if ($this->isRecapped($tire)) {
            return;
        }

        throw new DomainException(
            $tire->displayName().' es de dirección y no puede instalarse en tracción. Solo se permite en el 3.er eje o si está recapada.'
        );
    }

    public function canMount(Tire $tire, UnitPosition $position, ?FleetUnit $unit = null): bool
    {
        try {
            $this->assertCanMount($tire, $position, $unit);

            return true;
        } catch (DomainException) {
            return false;
        }
    }

    private function assertSizeFitsUnit(Tire $tire, FleetUnit $unit): void
    {
        $width = $unit->allowedTireWidth();
        if (! $width) {
            return;
        }

        $tireWidth = (int) ($tire->size?->width_mm ?? 0);
        if ($tireWidth !== $width) {
            throw new DomainException(
                $unit->plate.' lleva cubiertas lineales de '.$width.'. '.$tire->displayName().' es '.($tire->size?->code ?? 'otra medida').'.'
            );
        }
    }

    private function isRecapped(Tire $tire): bool
    {
        if ($tire->condition === TireCondition::Recapada) {
            return true;
        }

        return (int) ($tire->currentLifecycle?->life_number ?? 1) > 1;
    }
}
