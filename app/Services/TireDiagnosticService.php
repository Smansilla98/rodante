<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Sugerencias automáticas sobre el mapa de cubiertas de una unidad: desalineación
 * (diferencia de desgaste entre flanco izquierdo/derecho de una misma cubierta) y
 * rotación (diferencia de desgaste entre posiciones de un mismo eje). Inspirado en
 * el motor de "Diagnóstico y sugerencias" de cloudFleet, acotado a lo que ya
 * capturamos hoy (profundidad por zona) — sin presión ni hardware externo.
 *
 * La desalineación NO reimplementa el umbral: reusa `raises_alert`, que
 * MeasurementService ya calcula contra `tire_size.uneven_wear_threshold_mm`
 * (configurable por medida) al cargar la medición, y que además dispara la
 * incidencia DesgasteIrregular. Duplicar el umbral acá con un valor propio
 * podía mostrar un aviso en el mapa que no coincidiera con si se generó o no
 * esa incidencia.
 */
class TireDiagnosticService
{
    public const ROTATION_DELTA_MM = 2.0;

    /**
     * @param  Collection<int, array{position: \App\Models\UnitPosition, tire: ?\App\Models\Tire}>  $layout
     * @return array<int, list<array{code: string, label: string, detail: string}>>
     */
    public function forLayout(Collection $layout): array
    {
        $byPosition = [];

        foreach ($layout as $slot) {
            $tire = $slot['tire'];
            if (! $tire) {
                continue;
            }
            $alignment = $this->alignmentFlag($tire);
            if ($alignment) {
                $byPosition[$slot['position']->id][] = $alignment;
            }
        }

        $axles = $layout
            ->filter(fn ($slot) => ! $slot['position']->is_spare && $slot['tire'] !== null)
            ->groupBy(fn ($slot) => $slot['position']->axle_number);

        foreach ($axles as $axleNumber => $slots) {
            $treads = $slots
                ->filter(fn ($slot) => $slot['tire']->current_tread_min !== null)
                ->mapWithKeys(fn ($slot) => [$slot['position']->id => (float) $slot['tire']->current_tread_min]);

            if ($treads->count() < 2) {
                continue;
            }

            $delta = $treads->max() - $treads->min();
            if ($delta < self::ROTATION_DELTA_MM) {
                continue;
            }

            foreach ($treads as $positionId => $mm) {
                $byPosition[$positionId][] = [
                    'code' => 'ROTACION',
                    'label' => 'Rotación sugerida',
                    'detail' => "Desgaste desparejo en el eje {$axleNumber} (".round($delta, 1).'mm de diferencia).',
                ];
            }
        }

        return $byPosition;
    }

    /**
     * @return ?array{code: string, label: string, detail: string}
     */
    private function alignmentFlag(\App\Models\Tire $tire): ?array
    {
        $latest = $tire->measurements->sortByDesc('measured_at')->first();
        if (! $latest || ! $latest->raises_alert) {
            return null;
        }

        $izq = $latest->readings->first(fn ($r) => $r->zone?->code === 'FLANCO_IZQ')?->millimeters;
        $der = $latest->readings->first(fn ($r) => $r->zone?->code === 'FLANCO_DER')?->millimeters;
        $detail = ($izq !== null && $der !== null)
            ? 'Diferencia de '.round(abs((float) $izq - (float) $der), 1).'mm entre flanco izquierdo y derecho.'
            : 'La última medición marcó desgaste lateral desparejo.';

        return [
            'code' => 'ALINEACION',
            'label' => 'Posible desalineación',
            'detail' => $detail,
        ];
    }
}
