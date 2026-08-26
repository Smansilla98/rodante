<?php

namespace App\Services;

use App\Enums\TireStatus;
use App\Models\Tire;
use App\Models\User;
use App\Support\AccessScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PredictiveWearService
{
    public const CRITICAL_MM = 4.0;

    public const CATALOG_KM_PER_MM = 12000;

    public function __construct(
        private PredictiveNarrativeService $narrative,
    ) {}

    /**
     * @return array{
     *     current_mm: ?float,
     *     threshold_mm: float,
     *     wear_mm_per_1000km: float,
     *     remaining_km: ?int,
     *     confidence: string,
     *     source: string,
     *     status: string,
     *     samples: int,
     *     ai_enabled: bool,
     *     narrative: string,
     *     zones: list<array{name: string, mm: float, remaining_km: ?int}>
     * }
     */
    public function forecast(Tire $tire): array
    {
        $tire->loadMissing(['measurements.readings.zone', 'brand', 'model']);

        $current = $tire->current_tread_min !== null ? (float) $tire->current_tread_min : null;
        $points = $this->wearPoints($tire);
        [$rate, $source, $confidence] = $this->rateFromPoints($points);

        $status = 'unknown';
        $remainingKm = null;
        if ($current !== null) {
            $remainingMm = $current - self::CRITICAL_MM;
            if ($remainingMm <= 0) {
                $remainingKm = 0;
                $status = 'critical';
            } else {
                $mmPerKm = $rate / 1000;
                $remainingKm = $mmPerKm > 0 ? (int) round($remainingMm / $mmPerKm) : null;
                $status = ($remainingKm !== null && $remainingKm <= 8000) || $current <= 6 ? 'warn' : 'ok';
            }
        }

        $payload = [
            'current_mm' => $current,
            'threshold_mm' => self::CRITICAL_MM,
            'wear_mm_per_1000km' => round($rate, 3),
            'remaining_km' => $remainingKm,
            'confidence' => $confidence,
            'source' => $source,
            'status' => $status,
            'samples' => count($points),
            'ai_enabled' => (string) config('services.ai.key', '') !== '',
            'zones' => $this->zoneForecast($tire, $rate),
        ];
        $payload['narrative'] = $this->narrative->narrate($tire, $payload);

        return $payload;
    }

    /**
     * @return array{tires: LengthAwarePaginator, forecasts: array<int, array<string, mixed>>}
     */
    public function fleet(User $user): array
    {
        $query = Tire::query()->with([
            'brand',
            'model',
            'size',
            'measurements' => fn ($q) => $q->orderBy('measured_at')->orderBy('id'),
            'measurements.readings.zone',
        ]);
        AccessScope::tires($query, $user);

        $tires = $query
            ->where('status', '!=', TireStatus::DeBaja)
            ->orderByRaw('current_tread_min is null, current_tread_min asc')
            ->orderByDesc('accumulated_km')
            ->paginate(40)
            ->withQueryString();

        $forecasts = [];
        foreach ($tires as $tire) {
            $forecasts[$tire->id] = $this->forecast($tire);
        }

        return ['tires' => $tires, 'forecasts' => $forecasts];
    }

    /**
     * @return list<array{km: int, mm: float}>
     */
    private function wearPoints(Tire $tire): array
    {
        $points = [];
        foreach ($tire->measurements->sortBy([
            ['measured_at', 'asc'],
            ['id', 'asc'],
        ]) as $measurement) {
            if ($measurement->odometer === null) {
                continue;
            }
            $mm = $measurement->readings->min('millimeters');
            if ($mm === null) {
                continue;
            }
            $points[] = [
                'km' => (int) $measurement->odometer,
                'mm' => (float) $mm,
            ];
        }

        return $points;
    }

    /**
     * @param  list<array{km: int, mm: float}>  $points
     * @return array{0: float, 1: string, 2: string}
     */
    private function rateFromPoints(array $points): array
    {
        $catalog = 1000 / self::CATALOG_KM_PER_MM;
        if (count($points) < 2) {
            return [$catalog, 'catalog', 'low'];
        }

        $first = $points[0];
        $last = $points[array_key_last($points)];
        $deltaKm = $last['km'] - $first['km'];
        $deltaMm = $first['mm'] - $last['mm'];
        if ($deltaKm < 500 || $deltaMm <= 0) {
            return [$catalog, 'catalog', 'low'];
        }

        $rate = ($deltaMm / $deltaKm) * 1000;
        $confidence = ($deltaKm >= 5000 && count($points) >= 3) ? 'high' : 'medium';

        return [$rate, 'measurements', $confidence];
    }

    /**
     * @return list<array{name: string, mm: float, remaining_km: ?int}>
     */
    private function zoneForecast(Tire $tire, float $rate): array
    {
        $latest = $tire->measurements->sortByDesc('measured_at')->first();
        if (! $latest) {
            return [];
        }

        $mmPerKm = $rate / 1000;
        $zones = [];
        foreach ($latest->readings as $reading) {
            $mm = (float) $reading->millimeters;
            $remainingMm = $mm - self::CRITICAL_MM;
            $remainingKm = null;
            if ($remainingMm <= 0) {
                $remainingKm = 0;
            } elseif ($mmPerKm > 0) {
                $remainingKm = (int) round($remainingMm / $mmPerKm);
            }
            $zones[] = [
                'name' => $reading->zone?->name ?? 'Zona',
                'mm' => $mm,
                'remaining_km' => $remainingKm,
            ];
        }

        return $zones;
    }
}
