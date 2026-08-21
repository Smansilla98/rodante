<?php

namespace Tests\Feature;

use App\Models\Base;
use App\Models\CostEntry;
use App\Models\Supplier;
use App\Models\TireModel;
use App\Models\TirePurchase;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class BackfillPurchaseCostsTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    public function test_dry_run_then_redistributes_batch_cost_and_is_idempotent(): void
    {
        $purchase = $this->confirmedPurchase(2, 98001, 125.50);
        CostEntry::query()
            ->where('costable_type', TirePurchase::class)
            ->where('costable_id', $purchase->id)
            ->delete();
        $batch = $this->batchCost($purchase, 251.00);

        $this->artisan('rodante:backfill-purchase-costs', ['--dry-run' => true])
            ->expectsOutputToContain('redistribuiría 2 costos')
            ->assertSuccessful();
        $this->assertDatabaseHas('cost_entries', ['id' => $batch->id, 'amount' => 251.00]);

        $this->artisan('rodante:backfill-purchase-costs')->assertSuccessful();

        $this->assertDatabaseHas('cost_entries', [
            'id' => $batch->id,
            'amount' => 0,
            'notes' => 'Backfill redistribuido',
        ]);
        $this->assertSame(2, CostEntry::query()
            ->where('costable_type', TirePurchase::class)
            ->where('costable_id', $purchase->id)
            ->whereNotNull('tire_id')
            ->where('amount', 125.50)
            ->count());

        $this->artisan('rodante:backfill-purchase-costs')->assertSuccessful();
        $this->assertSame(2, CostEntry::query()
            ->where('costable_type', TirePurchase::class)
            ->where('costable_id', $purchase->id)
            ->whereNotNull('tire_id')
            ->count());
    }

    public function test_skips_ambiguous_amount(): void
    {
        $purchase = $this->confirmedPurchase(3, 98010);
        $batch = $this->batchCost($purchase, 10.00);

        $this->artisan('rodante:backfill-purchase-costs')
            ->expectsOutputToContain('el monto no se divide exactamente')
            ->assertSuccessful();

        $this->assertDatabaseHas('cost_entries', ['id' => $batch->id, 'amount' => 10.00]);
        $this->assertDatabaseMissing('cost_entries', [
            'costable_type' => TirePurchase::class,
            'costable_id' => $purchase->id,
            'amount' => 3.33,
        ]);
    }

    private function confirmedPurchase(int $quantity, int $firstNumber, ?float $unitCost = null): TirePurchase
    {
        $model = TireModel::with('sizes')->firstOrFail();
        $purchase = app(PurchaseService::class)->create([
            'supplier_id' => Supplier::firstOrFail()->id,
            'base_id' => Base::firstOrFail()->id,
            'purchased_at' => now()->toDateString(),
            'items' => [[
                'tire_brand_id' => $model->tire_brand_id,
                'tire_model_id' => $model->id,
                'tire_size_id' => $model->sizes->first()->id,
                'quantity' => $quantity,
                'first_number' => $firstNumber,
                'unit_cost' => $unitCost,
            ]],
        ], $this->admin);

        return app(PurchaseService::class)->confirm($purchase, $this->admin);
    }

    private function batchCost(TirePurchase $purchase, float $amount): CostEntry
    {
        return CostEntry::create([
            'company_id' => $purchase->company_id,
            'category' => 'PURCHASE',
            'amount' => $amount,
            'quantity' => 1,
            'currency' => 'ARS',
            'costable_type' => TirePurchase::class,
            'costable_id' => $purchase->id,
            'notes' => $purchase->number,
            'user_id' => $this->admin->id,
            'occurred_at' => now(),
        ]);
    }
}
