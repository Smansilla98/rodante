<?php

namespace Database\Seeders;

use App\Enums\TireStatus;
use App\Models\Base;
use App\Models\FleetUnit;
use App\Models\Supplier;
use App\Models\Tire;
use App\Models\TireModel;
use App\Models\TireSize;
use App\Models\UnitPosition;
use App\Models\User;
use App\Services\CouplingService;
use App\Services\PurchaseService;
use App\Services\TireOperationService;
use Illuminate\Database\Seeder;

/**
 * Completa el mapa de cubiertas en cada patente demo (todas las posiciones, incluido auxilio).
 * Idempotente: solo llena huecos vacíos y compra el stock que falte.
 */
class CompletePlateMapSeeder extends Seeder
{
    /** @var list<array{0:string,1:string}> */
    private const COUPLES = [
        ['HKH 448', 'FWI 093'],
        ['AC 363 CB', 'JNH 143'],
        ['OZK 888', 'FVX 336'],
        ['KUW 620', 'LRA 259'],
        ['NLO 982', 'DRO 762'],
    ];

    private int $nextNumber = 40000;

    /** @var list<int> */
    private array $pickedTireIds = [];

    public function run(): void
    {
        $admin = User::where('username', 'admin')->first();
        if (! $admin) {
            $this->command?->warn('CompletePlateMapSeeder: falta usuario admin. Corré DemoSeeder antes.');

            return;
        }

        $base = Base::where('code', 'SLT')->first() ?: Base::first();
        $supplier = Supplier::where('name', 'Pirelli Neumáticos')->first() ?: Supplier::first();
        if (! $base || ! $supplier) {
            $this->command?->warn('CompletePlateMapSeeder: faltan base o proveedor.');

            return;
        }

        $this->nextNumber = max(
            40000,
            (int) Tire::query()->max('individual_number') + 1,
        );

        $plates = collect(self::COUPLES)->flatten()->all();
        $units = FleetUnit::query()
            ->with(['configuration.positions', 'type', 'currentCouplingAsTractor', 'currentCouplingAsTrailer'])
            ->whereIn('plate', $plates)
            ->orderBy('plate')
            ->get();

        if ($units->isEmpty()) {
            $this->command?->warn('CompletePlateMapSeeder: no hay unidades demo. Corré DemoSeeder primero.');

            return;
        }

        $operations = app(TireOperationService::class);
        $filled = 0;

        foreach ($units as $unit) {
            $filled += $this->fillUnit($unit, $admin, $base->id, $supplier->id, $operations);
        }

        $this->ensureCouplings($admin);

        $this->command?->info("CompletePlateMapSeeder: {$filled} posiciones completadas en {$units->count()} patentes.");
    }

    private function fillUnit(
        FleetUnit $unit,
        User $admin,
        int $baseId,
        int $supplierId,
        TireOperationService $operations,
    ): int {
        $unit->loadMissing(['configuration.positions', 'type']);
        $positions = $unit->configuration?->positions?->sortBy('sort_order') ?? collect();
        if ($positions->isEmpty()) {
            return 0;
        }

        $occupied = Tire::query()
            ->whereHas('currentLocation', fn ($q) => $q->where('unit_id', $unit->id))
            ->with('currentLocation')
            ->get()
            ->keyBy(fn (Tire $tire) => (int) $tire->currentLocation?->position_id);

        $empty = $positions->filter(fn (UnitPosition $p) => ! $occupied->has((int) $p->id))->values();
        if ($empty->isEmpty()) {
            return 0;
        }

        $needed = [];
        foreach ($empty as $position) {
            [$modelCode, $sizeCode] = $this->pickProduct($unit, $position);
            $key = $modelCode.'|'.$sizeCode;
            $needed[$key] = ($needed[$key] ?? 0) + 1;
        }

        $this->ensureStock($needed, $admin, $baseId, $supplierId);

        $this->pickedTireIds = [];
        $installations = [];
        foreach ($empty as $position) {
            [$modelCode, $sizeCode] = $this->pickProduct($unit, $position);
            $tire = $this->takeStockTire($modelCode, $sizeCode);
            if (! $tire) {
                $this->command?->warn("Sin stock para {$modelCode} {$sizeCode} en {$unit->plate} / {$position->code}");

                continue;
            }
            $this->pickedTireIds[] = (int) $tire->id;
            $installations[] = [
                'tire_id' => $tire->id,
                'position_id' => $position->id,
            ];
        }

        if ($installations === []) {
            return 0;
        }

        $odometer = max(1, (int) $unit->current_odometer);
        if (! $unit->hasOdometer()) {
            $tractor = $unit->currentCouplingAsTrailer?->tractor;
            if ($tractor) {
                $odometer = max($odometer, (int) $tractor->current_odometer);
            } else {
                // Sin enganche: el servicio de operación en acoplado exige tractor; acoplamos antes si hace falta.
                $pair = collect(self::COUPLES)->first(fn ($pair) => $pair[1] === $unit->plate);
                if ($pair) {
                    $tractor = FleetUnit::where('plate', $pair[0])->first();
                    if ($tractor) {
                        try {
                            app(CouplingService::class)->couple(
                                $tractor,
                                $unit,
                                max(1, (int) $tractor->current_odometer),
                                $admin,
                                'Enganche demo para mapear '.$unit->plate,
                            );
                            $odometer = max(1, (int) $tractor->current_odometer);
                        } catch (\Throwable $e) {
                            $this->command?->warn("No se pudo enganchar {$pair[0]}↔{$unit->plate}: {$e->getMessage()}");
                        }
                    }
                }
            }
        }

        try {
            $operations->execute($unit->fresh(['type', 'configuration.positions']), [
                'odometer' => $odometer,
                'installations' => $installations,
                'notes' => 'Mapeo completo demo por patente',
            ], $admin);
        } catch (\Throwable $e) {
            $this->command?->warn("Falló el mapeo de {$unit->plate}: {$e->getMessage()}");

            return 0;
        }

        return count($installations);
    }

    /**
     * @return array{0:string,1:string} model code, size code
     */
    private function pickProduct(FleetUnit $unit, UnitPosition $position): array
    {
        $width = $unit->allowedTireWidth();
        $sizeCode = match ($width) {
            385 => TireSize::where('code', '385/65 R22.5')->exists() ? '385/65 R22.5' : '385/90 R22.5',
            295 => '295/80 R22.5',
            default => '295/80 R22.5',
        };

        if ($position->is_spare || $position->axle_role === 'AUXILIO') {
            if ($width) {
                return ['FR:01', $sizeCode];
            }

            return ['TR:01', $sizeCode];
        }

        $model = match ($position->axle_role) {
            'DIRECCION', 'DIRECCIONAL' => 'FH:01',
            'TRACCION' => 'TR:01',
            'ARRASTRE' => 'FR:01',
            default => $width ? 'FR:01' : 'TR:01',
        };

        // TR:01 no se fabrica en 385; en lineal forzar FR.
        if ($model === 'TR:01' && $width === 385) {
            $model = 'FR:01';
        }
        if ($model === 'FH:01' && $width === 385) {
            $model = 'FR:01';
        }

        return [$model, $sizeCode];
    }

    /**
     * @param  array<string,int>  $needed  keys "MODEL|SIZE"
     */
    private function ensureStock(array $needed, User $admin, int $baseId, int $supplierId): void
    {
        $items = [];
        foreach ($needed as $key => $qty) {
            [$modelCode, $sizeCode] = explode('|', $key, 2);
            $model = TireModel::where('code', $modelCode)->first();
            $size = TireSize::where('code', $sizeCode)->first();
            if (! $model || ! $size) {
                continue;
            }

            $available = Tire::query()
                ->where('status', TireStatus::Stock)
                ->where('tire_model_id', $model->id)
                ->where('tire_size_id', $size->id)
                ->count();

            $missing = $qty - $available;
            if ($missing <= 0) {
                continue;
            }

            $items[] = [
                'tire_brand_id' => $model->tire_brand_id,
                'tire_model_id' => $model->id,
                'tire_size_id' => $size->id,
                'quantity' => $missing,
                'first_number' => $this->allocateNumbers($missing),
            ];
        }

        if ($items === []) {
            return;
        }

        $purchases = app(PurchaseService::class);
        $purchase = $purchases->create([
            'supplier_id' => $supplierId,
            'base_id' => $baseId,
            'purchased_at' => now()->toDateString(),
            'notes' => 'Stock para mapeo completo por patente',
            'items' => $items,
        ], $admin);
        $purchases->confirm($purchase, $admin);
    }

    private function allocateNumbers(int $quantity): int
    {
        $start = $this->nextNumber;
        $this->nextNumber += $quantity;

        return $start;
    }

    private function takeStockTire(string $modelCode, string $sizeCode): ?Tire
    {
        $model = TireModel::where('code', $modelCode)->first();
        $size = TireSize::where('code', $sizeCode)->first();
        if (! $model || ! $size) {
            return null;
        }

        return Tire::query()
            ->where('status', TireStatus::Stock)
            ->where('tire_model_id', $model->id)
            ->where('tire_size_id', $size->id)
            ->when($this->pickedTireIds !== [], fn ($q) => $q->whereNotIn('id', $this->pickedTireIds))
            ->orderBy('individual_number')
            ->first();
    }

    private function ensureCouplings(User $admin): void
    {
        $couplings = app(CouplingService::class);
        foreach (self::COUPLES as [$tractorPlate, $trailerPlate]) {
            $tractor = FleetUnit::where('plate', $tractorPlate)->first();
            $trailer = FleetUnit::where('plate', $trailerPlate)->first();
            if (! $tractor || ! $trailer) {
                continue;
            }
            if ($tractor->currentCouplingAsTractor()->exists() || $trailer->currentCouplingAsTrailer()->exists()) {
                continue;
            }
            try {
                $couplings->couple(
                    $tractor,
                    $trailer,
                    max(1, (int) $tractor->current_odometer),
                    $admin,
                    'Enganche demo '.$tractorPlate,
                );
            } catch (\Throwable $e) {
                $this->command?->warn("Enganche {$tractorPlate}↔{$trailerPlate}: {$e->getMessage()}");
            }
        }
    }
}
