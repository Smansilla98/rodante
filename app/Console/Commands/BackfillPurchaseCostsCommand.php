<?php

namespace App\Console\Commands;

use App\Models\CostEntry;
use App\Models\TirePurchase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillPurchaseCostsCommand extends Command
{
    protected $signature = 'rodante:backfill-purchase-costs {--dry-run : Solo informa los cambios}';

    protected $description = 'Redistribuye costos históricos de compra entre sus cubiertas';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $skipped = 0;

        $purchaseIds = CostEntry::query()
            ->where('category', 'PURCHASE')
            ->where('costable_type', TirePurchase::class)
            ->whereNull('tire_id')
            ->where('amount', '>', 0)
            ->distinct()
            ->pluck('costable_id');

        TirePurchase::query()
            ->whereIn('id', $purchaseIds)
            ->where('status', TirePurchase::STATUS_CONFIRMED)
            ->with('items.tires')
            ->orderBy('id')
            ->each(function (TirePurchase $purchase) use ($dryRun, &$updated, &$skipped): void {
                $result = $this->allocationFor($purchase);
                if (! $result['allocations']) {
                    $skipped++;
                    $this->warn("{$purchase->number}: omitida ({$result['reason']}).");

                    return;
                }

                if ($dryRun) {
                    $updated++;
                    $this->line("{$purchase->number}: redistribuiría {$result['count']} costos.");

                    return;
                }

                DB::transaction(function () use ($purchase, $result): void {
                    /** @var CostEntry $template */
                    $template = $result['batch']->first();
                    foreach ($result['allocations'] as $tireId => $unitCents) {
                        CostEntry::create([
                            'company_id' => $template->company_id,
                            'category' => 'PURCHASE',
                            'amount' => $unitCents / 100,
                            'unit_price' => $unitCents / 100,
                            'quantity' => 1,
                            'currency' => $template->currency,
                            'costable_type' => TirePurchase::class,
                            'costable_id' => $purchase->id,
                            'tire_id' => $tireId,
                            'notes' => $purchase->number,
                            'user_id' => $template->user_id,
                            'occurred_at' => $template->occurred_at,
                        ]);
                    }

                    CostEntry::query()
                        ->whereKey($result['batch']->pluck('id'))
                        ->update([
                            'amount' => 0,
                            'notes' => 'Backfill redistribuido',
                        ]);
                });

                $updated++;
                $this->info("{$purchase->number}: {$result['count']} costos redistribuidos.");
            });

        $this->newLine();
        $this->info(($dryRun ? 'Simulados' : 'Procesados').": {$updated}; omitidos: {$skipped}.");

        return self::SUCCESS;
    }

    /**
     * @return array{allocations: array<int, int>, batch: \Illuminate\Support\Collection<int, CostEntry>, count: int, reason: string}
     */
    private function allocationFor(TirePurchase $purchase): array
    {
        $existing = CostEntry::query()
            ->where('category', 'PURCHASE')
            ->where('costable_type', TirePurchase::class)
            ->where('costable_id', $purchase->id)
            ->whereNotNull('tire_id')
            ->exists();
        if ($existing) {
            return $this->skip('ya tiene costos por cubierta');
        }

        $batch = CostEntry::query()
            ->where('category', 'PURCHASE')
            ->where('costable_type', TirePurchase::class)
            ->where('costable_id', $purchase->id)
            ->whereNull('tire_id')
            ->where('amount', '>', 0)
            ->get();
        if ($batch->isEmpty() || $batch->pluck('currency')->unique()->count() !== 1) {
            return $this->skip('lotes ausentes o con monedas distintas');
        }

        $tires = $purchase->items->flatMap->tires;
        if ($tires->isEmpty() || $tires->count() !== (int) $purchase->items->sum('quantity')) {
            return $this->skip('cantidad de cubiertas incompleta');
        }

        $totalCents = $batch->sum(fn (CostEntry $entry) => $this->cents($entry->amount));
        $byItem = [];
        $itemTotalCents = 0;
        $hasAllUnitCosts = true;
        foreach ($purchase->items as $item) {
            if ($item->unit_cost === null || (float) $item->unit_cost <= 0) {
                $hasAllUnitCosts = false;
                break;
            }
            $unitCents = $this->cents($item->unit_cost);
            $itemTotalCents += $unitCents * $item->tires->count();
            foreach ($item->tires as $tire) {
                $byItem[$tire->id] = $unitCents;
            }
        }

        if ($hasAllUnitCosts && $itemTotalCents === $totalCents) {
            return [
                'allocations' => $byItem,
                'batch' => $batch,
                'count' => count($byItem),
                'reason' => '',
            ];
        }

        if ($totalCents % $tires->count() !== 0) {
            return $this->skip('el monto no se divide exactamente');
        }

        $unitCents = intdiv($totalCents, $tires->count());

        return [
            'allocations' => $tires->mapWithKeys(fn ($tire) => [$tire->id => $unitCents])->all(),
            'batch' => $batch,
            'count' => $tires->count(),
            'reason' => '',
        ];
    }

    private function cents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    /**
     * @return array{allocations: array<int, int>, batch: \Illuminate\Support\Collection<int, CostEntry>, count: int, reason: string}
     */
    private function skip(string $reason): array
    {
        return [
            'allocations' => [],
            'batch' => collect(),
            'count' => 0,
            'reason' => $reason,
        ];
    }
}
