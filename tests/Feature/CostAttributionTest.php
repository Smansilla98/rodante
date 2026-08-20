<?php

namespace Tests\Feature;

use App\Models\CostEntry;
use App\Models\Supplier;
use App\Models\TireModel;
use App\Models\TireSize;
use App\Models\UnitPosition;
use App\Services\PurchaseService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class CostAttributionTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    public function test_legacy_cost_per_km_formula_unchanged(): void
    {
        [$tire] = $this->purchaseTires(1, 91001);
        $tire->update(['accumulated_km' => 10000]);

        CostEntry::create([
            'company_id' => $this->admin->company_id,
            'category' => 'REPAIR',
            'amount' => 2500,
            'currency' => 'ARS',
            'tire_id' => $tire->id,
            'user_id' => $this->admin->id,
            'occurred_at' => now(),
        ]);

        $page = app(ReportService::class)->costPerKm($this->admin);
        $row = $page->getCollection()->firstWhere('id', $tire->id);

        $this->assertNotNull($row);
        $this->assertSame(2500.0, (float) $row->cost_total);
        $this->assertSame(0.25, (float) $row->cost_per_km);
    }

    public function test_cost_by_position_splits_entries_for_same_tire(): void
    {
        [$tire] = $this->purchaseTires(1, 91002);
        $unit = $this->createTractor();
        $positions = UnitPosition::query()
            ->where('unit_configuration_id', $unit->unit_configuration_id)
            ->where('is_spare', false)
            ->orderBy('sort_order')
            ->take(2)
            ->get();

        $this->assertCount(2, $positions);

        CostEntry::create([
            'company_id' => $this->admin->company_id,
            'category' => 'REPAIR',
            'amount' => 1000,
            'unit_price' => 1000,
            'quantity' => 1,
            'currency' => 'ARS',
            'tire_id' => $tire->id,
            'fleet_unit_id' => $unit->id,
            'unit_position_id' => $positions[0]->id,
            'user_id' => $this->admin->id,
            'occurred_at' => now()->subDay(),
        ]);
        CostEntry::create([
            'company_id' => $this->admin->company_id,
            'category' => 'RECAP',
            'amount' => 500,
            'unit_price' => 500,
            'quantity' => 1,
            'currency' => 'ARS',
            'tire_id' => $tire->id,
            'fleet_unit_id' => $unit->id,
            'unit_position_id' => $positions[1]->id,
            'user_id' => $this->admin->id,
            'occurred_at' => now(),
        ]);

        $byPosition = app(ReportService::class)->costByPosition($this->admin)->keyBy('unit_position_id');
        $this->assertSame(1000.0, (float) $byPosition[$positions[0]->id]->total_amount);
        $this->assertSame(500.0, (float) $byPosition[$positions[1]->id]->total_amount);

        $byUnit = app(ReportService::class)->costByUnit($this->admin)->firstWhere('fleet_unit_id', $unit->id);
        $this->assertSame(1500.0, (float) $byUnit->total_amount);
        $this->assertSame(2, (int) $byUnit->entries_count);
        $this->assertSame(1, (int) $byUnit->tire_count);

        // $/km sigue sumando todos los tire_id, sin importar posición
        $tire->update(['accumulated_km' => 3000]);
        $page = app(ReportService::class)->costPerKm($this->admin);
        $row = $page->getCollection()->firstWhere('id', $tire->id);
        $this->assertSame(1500.0, (float) $row->cost_total);
        $this->assertSame(0.5, (float) $row->cost_per_km);
    }

    public function test_purchase_records_one_entry_per_tire_with_unit_price(): void
    {
        $model = TireModel::first();
        $size = $model->sizes()->orderBy('code')->first() ?: TireSize::first();

        $purchase = app(PurchaseService::class)->create([
            'supplier_id' => Supplier::first()->id,
            'base_id' => \App\Models\Base::first()->id,
            'purchased_at' => now()->toDateString(),
            'items' => [[
                'tire_brand_id' => $model->tire_brand_id,
                'tire_model_id' => $model->id,
                'tire_size_id' => $size->id,
                'quantity' => 2,
                'first_number' => 91010,
                'unit_cost' => 180000.50,
            ]],
        ], $this->admin);

        app(PurchaseService::class)->confirm($purchase, $this->admin);

        $this->assertDatabaseCount('cost_entries', 2);
        $this->assertDatabaseHas('cost_entries', [
            'category' => 'PURCHASE',
            'amount' => 180000.50,
            'unit_price' => 180000.50,
            'quantity' => 1,
        ]);
        $this->assertSame(2, CostEntry::whereNotNull('tire_id')->where('category', 'PURCHASE')->count());
    }

    public function test_cost_attribution_report_page_renders(): void
    {
        $this->get(route('reports.cost-attribution'))->assertOk();
    }
}
