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
        if (! $application || $application === TireApplication::Mixto) {
            return;
        }

        $role = (string) $position->axle_role;
        $isSteer = in_array($role, ['DIRECCION', 'DIRECCIONAL'], true);
        $isDrive = $role === 'TRACCION';
        $isTrailer = $role === 'ARRASTRE';
        $isTagDrive = $isDrive && ($position->is_liftable || (int) $position->axle_number >= 3);

        if ($application === TireApplication::Direccion) {
            if ($isSteer) {
                return;
            }
            if ($isDrive && ((int) $position->axle_number === 3 || $this->isRecapped($tire))) {
                return;
            }
            if ($isDrive) {
                throw new DomainException(
                    $tire->displayName().' es de dirección y no puede instalarse en tracción. Solo se permite en el 3.er eje o si está recapada.'
                );
            }
            throw new DomainException(
                $tire->displayName().' es de dirección y no puede instalarse en '.$this->roleLabel($role).'.'
            );
        }

        if ($application === TireApplication::Traccion) {
            if ($isDrive) {
                return;
            }
            if ($isSteer) {
                throw new DomainException(
                    $tire->displayName().' es de tracción y no puede instalarse en dirección.'
                );
            }
            throw new DomainException(
                $tire->displayName().' es de tracción y no puede instalarse en '.$this->roleLabel($role).'.'
            );
        }

        if ($application === TireApplication::Arrastre) {
            if ($isTrailer || $isTagDrive) {
                return;
            }
            if ($isSteer) {
                throw new DomainException(
                    $tire->displayName().' es de arrastre y no puede instalarse en dirección.'
                );
            }
            if ($isDrive) {
                throw new DomainException(
                    $tire->displayName().' es de arrastre y no puede instalarse en tracción motriz. Use eje tag/elevable o posición de arrastre.'
                );
            }
            throw new DomainException(
                $tire->displayName().' es de arrastre y no puede instalarse en '.$this->roleLabel($role).'.'
            );
        }
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

    public function neededApplication(?Tire $mounted, UnitPosition $position): ?TireApplication
    {
        if ($position->is_spare || $position->axle_role === 'AUXILIO') {
            return null;
        }

        $fromTire = $mounted?->model?->application;
        if ($fromTire && $fromTire !== TireApplication::Mixto) {
            return $fromTire;
        }

        return match ($position->axle_role) {
            'DIRECCION', 'DIRECCIONAL' => TireApplication::Direccion,
            'TRACCION' => TireApplication::Traccion,
            'ARRASTRE' => TireApplication::Arrastre,
            default => null,
        };
    }

    public function assertReplacementFits(Tire $replacement, UnitPosition $position, ?FleetUnit $unit = null, ?Tire $current = null): void
    {
        $replacement->loadMissing('model', 'size', 'currentLifecycle');
        $current?->loadMissing('model', 'size');

        $this->assertCanMount($replacement, $position, $unit);
        $this->assertSizeMatchesMounted($replacement, $current);

        $needed = $this->neededApplication($current, $position);
        $got = $replacement->model?->application;
        if ($needed && $got && $got !== TireApplication::Mixto && $got !== $needed) {
            throw new DomainException(
                'El recambio tiene que ser de '.$needed->label().'. '.$replacement->displayName().' es de '.$got->label().'.'
            );
        }
    }

    public function canReplace(Tire $replacement, UnitPosition $position, ?FleetUnit $unit = null, ?Tire $current = null): bool
    {
        try {
            $this->assertReplacementFits($replacement, $position, $unit, $current);

            return true;
        } catch (DomainException) {
            return false;
        }
    }

    /**
     * @return array{role: string, application: ?string, application_label: ?string, size: ?string, rules: list<string>}
     */
    public function fitGuide(?Tire $mounted, UnitPosition $position, ?FleetUnit $unit = null): array
    {
        $needed = $this->neededApplication($mounted, $position);
        $size = $mounted?->size?->code ?? ($unit?->allowedTireWidth() ? (string) $unit->allowedTireWidth() : null);
        $rules = [];

        if ($position->is_spare || $position->axle_role === 'AUXILIO') {
            $rules[] = 'Auxilio: acepta cualquier nomenclatura.';
        } else {
            $rules[] = match ($position->axle_role) {
                'DIRECCION', 'DIRECCIONAL' => 'Dirección: solo cubiertas de Dirección (o Mixta).',
                'TRACCION' => $position->is_liftable || (int) $position->axle_number >= 3
                    ? 'Eje tag/elevable: Tracción, Arrastre o Dirección recapada / 3.er eje.'
                    : 'Tracción: cubiertas de Tracción (o Mixta). Dirección solo si está recapada o es 3.er eje.',
                'ARRASTRE' => 'Arrastre: cubiertas de Arrastre (o Mixta).',
                default => 'Respete la nomenclatura de la posición.',
            };
        }

        if ($size) {
            $rules[] = 'Medida requerida: '.$size.($mounted ? ' (igual a la que sale)' : '').'.';
        }
        if ($unit?->duty?->prefersWinterTires()) {
            $rules[] = 'Unidad en nieve: priorice modelos aptos nieve/cadenas.';
        }

        return [
            'role' => $position->axleRole(),
            'application' => $needed?->value,
            'application_label' => $needed?->label(),
            'size' => $size,
            'rules' => $rules,
        ];
    }

    public function dutyHint(?FleetUnit $unit): ?string
    {
        return $unit?->duty?->hint();
    }

    public function prefersWinter(?FleetUnit $unit): bool
    {
        return (bool) $unit?->duty?->prefersWinterTires();
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

    private function assertSizeMatchesMounted(Tire $replacement, ?Tire $current): void
    {
        if (! $current?->size_id) {
            return;
        }

        if ((int) $replacement->size_id !== (int) $current->size_id) {
            throw new DomainException(
                'El recambio tiene que ser medida '.($current->size?->code ?? 'igual').'. '
                .$replacement->displayName().' es '.($replacement->size?->code ?? 'otra medida').'.'
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

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'DIRECCION', 'DIRECCIONAL' => 'dirección',
            'TRACCION' => 'tracción',
            'ARRASTRE' => 'arrastre',
            default => strtolower($role),
        };
    }
}
