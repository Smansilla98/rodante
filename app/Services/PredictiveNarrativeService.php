<?php

namespace App\Services;

use App\Models\Tire;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PredictiveNarrativeService
{
    /**
     * @param  array<string, mixed>  $forecast
     */
    public function narrate(Tire $tire, array $forecast): string
    {
        $local = $this->local($tire, $forecast);
        $key = (string) config('services.ai.key', '');
        if ($key === '') {
            return $local;
        }

        try {
            $remote = $this->fromProvider($key, $tire, $forecast);
            $remote = is_string($remote) ? trim($remote) : '';

            return $remote !== '' ? $remote : $local;
        } catch (\Throwable $e) {
            Log::warning('Narrativa IA no disponible', ['error' => $e->getMessage()]);

            return $local;
        }
    }

    /**
     * @param  array<string, mixed>  $forecast
     */
    public function local(Tire $tire, array $forecast): string
    {
        $name = $tire->displayName();
        $current = $forecast['current_mm'] ?? null;
        $remaining = $forecast['remaining_km'] ?? null;
        $rate = $forecast['wear_mm_per_1000km'] ?? null;
        $source = $forecast['source'] ?? 'catalog';
        $status = $forecast['status'] ?? 'unknown';

        if ($current === null) {
            return $name.': todavía no hay mediciones de profundidad. El pronóstico aparece cuando se carga al menos una medida.';
        }

        $mm = number_format((float) $current, 1, ',', '.');
        $parts = [$name.' está en '.$mm.' mm de banda (umbral crítico 4 mm).'];

        if ($status === 'critical') {
            $parts[] = 'Ya está en o por debajo del umbral: conviene darla de baja o recapar según el estado de carcasa.';
        } elseif ($remaining !== null) {
            $parts[] = 'A este ritmo quedarían unos '.number_format((int) $remaining, 0, ',', '.').' km hasta 4 mm.';
        }

        if ($rate) {
            $parts[] = 'Desgaste estimado: '.number_format((float) $rate, 3, ',', '.').' mm cada 1.000 km.';
        }

        $parts[] = $source === 'measurements'
            ? 'El cálculo usa las mediciones con odómetro de esta cubierta.'
            : 'Hay pocas mediciones con km: se usa una estimación de catálogo (1 mm cada 12.000 km), no un dato de esta unidad.';

        return implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $forecast
     */
    private function fromProvider(string $key, Tire $tire, array $forecast): ?string
    {
        $response = Http::timeout(8)
            ->withToken($key)
            ->acceptJson()
            ->post((string) config('services.ai.url'), [
                'model' => config('services.ai.model'),
                'temperature' => 0.2,
                'max_tokens' => 180,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Sos un analista de flota de neumáticos. Respondé en español rioplatense, máximo 80 palabras. Usá SOLO los números del JSON. No inventes mediciones, km ni fotos. No indiques dar de baja: sugerí revisión del jefe de sector.',
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode([
                            'tire' => $tire->displayName(),
                            'forecast' => $forecast,
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ]);

        if (! $response->successful()) {
            return null;
        }

        $text = $response->json('choices.0.message.content');

        return is_string($text) ? $text : null;
    }
}
