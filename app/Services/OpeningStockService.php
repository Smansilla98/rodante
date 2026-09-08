<?php

namespace App\Services;

use App\Enums\LocationKind;
use App\Enums\MovementType;
use App\Enums\TireCondition;
use App\Enums\TireStatus;
use App\Exceptions\DomainException;
use App\Models\Base;
use App\Models\Tire;
use App\Models\TireBrand;
use App\Models\TireLifecycle;
use App\Models\TireModel;
use App\Models\TireSize;
use App\Models\User;
use App\Support\AccessScope;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class OpeningStockService
{
    public function __construct(
        private LocationService $locations,
        private AuditService $audit,
        private DocumentNumberService $numbers,
    ) {}

    /**
     * Carga cubiertas ya existentes (previas al sistema) en stock de una base.
     * No crea compra, proveedor ni costo.
     *
     * @param  list<array{
     *     individual_number?: int|null,
     *     tire_brand_id: int,
     *     tire_model_id: int,
     *     tire_size_id: int,
     *     dot?: string|null,
     *     accumulated_km?: int,
     *     condition?: string|null
     * }>  $rows
     * @return list<Tire>
     */
    public function importStock(array $rows, Base $base, User $user, CarbonInterface|string|null $asOf = null): array
    {
        if ($rows === []) {
            throw new DomainException('No hay filas para importar.');
        }

        if (! AccessScope::seesEverything($user) && ! in_array((int) $base->id, AccessScope::visibleBaseIds($user), true)) {
            throw new DomainException('No tenés acceso a esa base.');
        }

        $asOfDate = $asOf ? \Illuminate\Support\Carbon::parse($asOf)->startOfDay() : now()->startOfDay();
        $companyId = (int) $user->company_id;

        return DB::transaction(function () use ($rows, $base, $user, $asOfDate, $companyId) {
            $this->numbers->ensureAtLeast(
                $companyId,
                'tire_individual',
                (int) Tire::query()->where('company_id', $companyId)->max('individual_number'),
            );

            $created = [];
            foreach ($rows as $index => $row) {
                $line = $index + 1;
                $this->assertItemMatchesCatalog($row);

                $number = isset($row['individual_number']) && $row['individual_number'] !== null && $row['individual_number'] !== ''
                    ? (int) $row['individual_number']
                    : $this->numbers->nextValue($companyId, 'tire_individual');

                if ($number < 1) {
                    throw new DomainException("Fila {$line}: el número de cubierta es inválido.");
                }

                if (Tire::where('company_id', $companyId)->where('individual_number', $number)->exists()) {
                    throw new DomainException("Fila {$line}: el número {$number} ya existe en la empresa.");
                }

                $this->numbers->ensureAtLeast($companyId, 'tire_individual', $number);

                $dot = Tire::normalizeDot($row['dot'] ?? null);
                if ($dot && Tire::where('company_id', $companyId)->where('dot', $dot)->exists()) {
                    throw new DomainException("Fila {$line}: el DOT {$dot} ya está cargado en otra cubierta.");
                }

                $condition = $this->resolveCondition($row['condition'] ?? null, $line);
                $km = max(0, (int) ($row['accumulated_km'] ?? 0));

                $tire = Tire::create([
                    'company_id' => $companyId,
                    'individual_number' => $number,
                    'dot' => $dot,
                    'tire_brand_id' => $row['tire_brand_id'],
                    'tire_model_id' => $row['tire_model_id'],
                    'tire_size_id' => $row['tire_size_id'],
                    'tire_purchase_item_id' => null,
                    'status' => TireStatus::Stock,
                    'condition' => $condition,
                    'accumulated_km' => $km,
                    'purchased_at' => $asOfDate,
                ]);

                $life = TireLifecycle::create([
                    'tire_id' => $tire->id,
                    'life_number' => 1,
                    'started_by' => 'PUNTO_DE_PARTIDA',
                    'started_at' => $asOfDate,
                    'condition_at_start' => $condition->value,
                ]);
                $tire->update(['current_lifecycle_id' => $life->id]);

                $this->locations->place($tire, LocationKind::Stock, $base->id);

                $tire->movements()->create([
                    'type' => MovementType::OpeningIn,
                    'occurred_at' => $asOfDate,
                    'to_base_id' => $base->id,
                    'user_id' => $user->id,
                    'notes' => 'Ingreso por punto de partida (stock previo)',
                    'created_at' => now(),
                ]);

                $created[] = $tire;
            }

            $this->audit->log('opening.imported', $base, null, [
                'base' => $base->name,
                'count' => count($created),
                'as_of' => $asOfDate->toDateString(),
            ]);

            return $created;
        });
    }

    /**
     * Parsea CSV de stock previo. Primera fila = encabezado.
     * Columnas: Numero;Marca;Modelo;Medida;DOT;Km;Condicion
     *
     * @return list<array<string, mixed>>
     */
    public function parseStockCsv(string $path): array
    {
        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            throw new DomainException('El archivo está vacío.');
        }

        $delimiter = substr_count($raw, ';') >= substr_count($raw, ',') ? ';' : ',';
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new DomainException('No se pudo leer el archivo.');
        }

        $header = fgetcsv($handle, 0, $delimiter);
        if (! $header) {
            fclose($handle);
            throw new DomainException('El archivo está vacío.');
        }

        $rows = [];
        $line = 1;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;
            if ($row === [null] || collect($row)->filter(fn ($v) => $v !== null && $v !== '')->isEmpty()) {
                continue;
            }

            $brandName = trim((string) ($row[1] ?? ''));
            $modelCode = trim((string) ($row[2] ?? ''));
            $sizeCode = trim((string) ($row[3] ?? ''));

            $brand = TireBrand::where('name', $brandName)->first();
            $model = TireModel::where('code', $modelCode)->first();
            $size = TireSize::where('code', $sizeCode)->first();

            if (! $brand || ! $model || ! $size) {
                fclose($handle);
                throw new DomainException(
                    "Fila {$line}: marca, modelo y medida son obligatorios y tienen que existir en el catálogo."
                );
            }

            $numberRaw = trim((string) ($row[0] ?? ''));
            $rows[] = [
                'individual_number' => $numberRaw !== '' ? (int) $numberRaw : null,
                'tire_brand_id' => $brand->id,
                'tire_model_id' => $model->id,
                'tire_size_id' => $size->id,
                'dot' => isset($row[4]) && trim((string) $row[4]) !== '' ? trim((string) $row[4]) : null,
                'accumulated_km' => isset($row[5]) && trim((string) $row[5]) !== ''
                    ? (int) preg_replace('/\D+/', '', (string) $row[5])
                    : 0,
                'condition' => isset($row[6]) ? trim((string) $row[6]) : null,
            ];
        }
        fclose($handle);

        if ($rows === []) {
            throw new DomainException('No hay filas válidas.');
        }

        return $rows;
    }

    private function assertItemMatchesCatalog(array $item): void
    {
        $model = TireModel::with('brand', 'sizes')->find($item['tire_model_id'] ?? null);
        if (! $model) {
            throw new DomainException('Elegí un modelo de cubierta.');
        }
        if ((int) $model->tire_brand_id !== (int) $item['tire_brand_id']) {
            throw new DomainException(
                $model->code.' es de '.$model->brand->name.'. No se puede cargar con otra marca.'
            );
        }
        if (! $model->sizes->contains('id', (int) $item['tire_size_id'])) {
            throw new DomainException(
                $model->brand->name.' '.$model->code.' no se fabrica en esa medida.'
            );
        }
    }

    private function resolveCondition(?string $raw, int $line): TireCondition
    {
        if ($raw === null || trim($raw) === '') {
            return TireCondition::Usada;
        }

        $normalized = strtoupper(str_replace([' ', '-'], '_', trim($raw)));
        $aliases = [
            'NUEVA' => TireCondition::Nueva,
            'NUEVA_USADA' => TireCondition::NuevaUsada,
            'USADA' => TireCondition::Usada,
            'RECAPADA' => TireCondition::Recapada,
            'REPARADA' => TireCondition::Reparada,
            'REPARADA_(PARCHE)' => TireCondition::Reparada,
            'PARCHE' => TireCondition::Reparada,
        ];

        if (isset($aliases[$normalized])) {
            return $aliases[$normalized];
        }

        foreach (TireCondition::cases() as $case) {
            if (strtoupper($case->label()) === strtoupper(trim($raw)) || $case->value === $normalized) {
                return $case;
            }
        }

        throw new DomainException(
            "Fila {$line}: condición inválida «{$raw}». Usá NUEVA, NUEVA_USADA, USADA, RECAPADA o REPARADA."
        );
    }
}
