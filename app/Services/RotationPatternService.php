<?php

namespace App\Services;

use App\Models\FleetUnit;
use App\Models\UnitPosition;
use Illuminate\Support\Collection;

class RotationPatternService
{
    /**
     * Esquemas de rotación al estilo Kananfleet / Administra Flotilla.
     *
     * @param  Collection<int, array{position:UnitPosition, tire:?\App\Models\Tire}>  $layout
     * @return list<array{code:string,name:string,hint:string,pairs:list<array{0:int,1:int}>,ready:bool,blocked:?string}>
     */
    public function forLayout(Collection $layout, PositionFitService $fit, ?FleetUnit $unit = null): array
    {
        $live = $layout->filter(fn (array $slot) => ! $slot['position']->is_spare)->values();
        $byAxle = $live->groupBy(fn (array $slot) => (int) $slot['position']->axle_number)->sortKeys();

        $steer = $this->steerPair($byAxle);
        $tandem = $this->tandemDualAxles($byAxle);

        $patterns = [];
        foreach ([
            ['longitudinal', 'Longitudinal', 'Dirección L↔R. En el tándem, misma banda y misma dual entre ejes.', array_merge($steer, $this->longitudinal($tandem))],
            ['cruzado', 'En X', 'Dirección L↔R. En el tándem, cruce interna↔externa del mismo lado.', array_merge($steer, $this->lateralX($tandem))],
            ['diagonal', 'Diagonal', 'Dirección L↔R. En el tándem, cruce de lado entre ejes.', array_merge($steer, $this->diagonal($tandem))],
        ] as [$code, $name, $hint, $pairs]) {
            if ($pairs === []) {
                continue;
            }
            $patterns[] = $this->describe($code, $name, $hint, $pairs, $live, $fit, $unit);
        }

        return $patterns;
    }

    /**
     * @param  Collection<int, Collection<int, array{position:UnitPosition, tire:?\App\Models\Tire}>>  $byAxle
     * @return list<array{0:int,1:int}>
     */
    private function steerPair(Collection $byAxle): array
    {
        $first = $byAxle->first();
        if (! $first) {
            return [];
        }

        $steer = $first->filter(fn (array $slot) => in_array($slot['position']->axle_role, ['DIRECCION', 'DIRECCIONAL'], true)
            && $slot['position']->dual === null);
        if ($steer->count() !== 2) {
            return [];
        }

        $ids = $steer->sortBy(fn (array $slot) => $slot['position']->side === 'IZQ' ? 0 : 1)
            ->pluck('position.id')
            ->values();

        return [[(int) $ids[0], (int) $ids[1]]];
    }

    /**
     * @param  Collection<int, Collection<int, array{position:UnitPosition, tire:?\App\Models\Tire}>>  $byAxle
     * @return list<Collection<int, array{position:UnitPosition, tire:?\App\Models\Tire}>>
     */
    private function tandemDualAxles(Collection $byAxle): array
    {
        $duals = $byAxle->filter(fn (Collection $slots) => $slots->filter(fn (array $slot) => (bool) $slot['position']->dual)->count() === 4);
        if ($duals->count() < 2) {
            return [];
        }

        $keys = $duals->keys()->values();

        return [
            $duals[$keys[$keys->count() - 2]],
            $duals[$keys[$keys->count() - 1]],
        ];
    }

    private function slotKey(UnitPosition $position): string
    {
        return $position->side.'|'.($position->dual ?? 'SIMPLE');
    }

    /**
     * @param  list<Collection<int, array{position:UnitPosition, tire:?\App\Models\Tire}>>  $tandem
     * @return list<array{0:int,1:int}>
     */
    private function longitudinal(array $tandem): array
    {
        return $this->pairTandem($tandem, function (UnitPosition $position) {
            return $this->slotKey($position);
        });
    }

    /**
     * @param  list<Collection<int, array{position:UnitPosition, tire:?\App\Models\Tire}>>  $tandem
     * @return list<array{0:int,1:int}>
     */
    private function lateralX(array $tandem): array
    {
        $flip = ['EXT' => 'INT', 'INT' => 'EXT'];

        return $this->pairTandem($tandem, function (UnitPosition $position) use ($flip) {
            if (! isset($flip[$position->dual])) {
                return null;
            }

            return $position->side.'|'.$flip[$position->dual];
        });
    }

    /**
     * @param  list<Collection<int, array{position:UnitPosition, tire:?\App\Models\Tire}>>  $tandem
     * @return list<array{0:int,1:int}>
     */
    private function diagonal(array $tandem): array
    {
        $flipSide = ['IZQ' => 'DER', 'DER' => 'IZQ'];

        return $this->pairTandem($tandem, function (UnitPosition $position) use ($flipSide) {
            $side = $flipSide[$position->side] ?? null;
            if (! $side) {
                return null;
            }

            return $side.'|'.($position->dual ?? 'SIMPLE');
        });
    }

    /**
     * @param  list<Collection<int, array{position:UnitPosition, tire:?\App\Models\Tire}>>  $tandem
     * @param  callable(UnitPosition): ?string  $rearKey
     * @return list<array{0:int,1:int}>
     */
    private function pairTandem(array $tandem, callable $rearKey): array
    {
        if ($tandem === []) {
            return [];
        }

        [$front, $rear] = $tandem;
        $rearBy = $rear->keyBy(fn (array $slot) => $this->slotKey($slot['position']));
        $pairs = [];

        foreach ($front as $slot) {
            $key = $rearKey($slot['position']);
            if (! $key) {
                continue;
            }
            $match = $rearBy->get($key);
            if ($match) {
                $pairs[] = [(int) $slot['position']->id, (int) $match['position']->id];
            }
        }

        return $pairs;
    }

    /**
     * @param  list<array{0:int,1:int}>  $pairs
     * @param  Collection<int, array{position:UnitPosition, tire:?\App\Models\Tire}>  $live
     * @return array{code:string,name:string,hint:string,pairs:list<array{0:int,1:int}>,ready:bool,blocked:?string}
     */
    private function describe(string $code, string $name, string $hint, array $pairs, Collection $live, PositionFitService $fit, ?FleetUnit $unit = null): array
    {
        $ready = true;
        $blocked = null;

        foreach ($pairs as [$a, $b]) {
            $slotA = $live->first(fn (array $slot) => (int) $slot['position']->id === $a);
            $slotB = $live->first(fn (array $slot) => (int) $slot['position']->id === $b);
            if (! $slotA || ! $slotB || ! $slotA['tire'] || ! $slotB['tire']) {
                $ready = false;
                $blocked = 'Faltan cubiertas en las ubicaciones del esquema.';
                break;
            }
            if (! $fit->canMount($slotA['tire'], $slotB['position'], $unit) || ! $fit->canMount($slotB['tire'], $slotA['position'], $unit)) {
                $ready = false;
                $blocked = 'El esquema cruza cubiertas incompatibles con el eje destino.';
                break;
            }
        }

        return [
            'code' => $code,
            'name' => $name,
            'hint' => $hint,
            'pairs' => $pairs,
            'ready' => $ready,
            'blocked' => $blocked,
        ];
    }
}
