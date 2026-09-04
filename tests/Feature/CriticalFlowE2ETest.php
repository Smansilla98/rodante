<?php

namespace Tests\Feature;

use App\Models\Base;
use App\Models\MovementReason;
use App\Models\RetreadShop;
use App\Models\Supplier;
use App\Models\Tire;
use App\Models\TireModel;
use App\Models\TirePurchase;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class CriticalFlowE2ETest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    public function test_login_purchase_confirm_install_remove_and_open_work_order(): void
    {
        $this->seedDomain();
        auth()->logout();
        $this->get('/login');
        $csrf = csrf_token();
        $this->post('/login', ['username' => 'admin-test', 'password' => 'password', '_token' => $csrf])
            ->assertRedirect(route('dashboard'));
        $csrf = csrf_token();

        $model = TireModel::with('sizes')->firstOrFail();
        $size = $model->sizes->first();
        $firstNumber = 88001;
        $this->post('/compras', [
            '_token' => $csrf,
            'supplier_id' => Supplier::firstOrFail()->id,
            'base_id' => Base::firstOrFail()->id,
            'purchased_at' => now()->toDateString(),
            'items' => [[
                'tire_brand_id' => $model->tire_brand_id,
                'tire_model_id' => $model->id,
                'tire_size_id' => $size->id,
                'quantity' => 1,
                'first_number' => $firstNumber,
            ]],
        ])->assertRedirect();

        $purchase = TirePurchase::latest('id')->firstOrFail();
        $this->post("/compras/{$purchase->id}/confirmar", ['_token' => $csrf])->assertSessionHas('success');
        $tire = Tire::where('individual_number', $firstNumber)->firstOrFail();
        $unit = $this->createTractor();
        $position = $unit->configuration->positions()->where('is_spare', false)->firstOrFail();

        $this->post("/unidades/{$unit->id}/operacion", [
            '_token' => $csrf,
            'odometer' => 100001,
            'installations' => [[
                'tire_id' => $tire->id,
                'position_id' => $position->id,
                'expect_empty' => true,
            ]],
        ])->assertSessionHas('success');
        $this->assertSame('INSTALADA', $tire->fresh()->status->value);

        $this->post("/unidades/{$unit->id}/operacion", [
            '_token' => $csrf,
            'odometer' => 100010,
            'removals' => [[
                'tire_id' => $tire->id,
                'position_id' => $position->id,
                'reason_id' => MovementReason::where('applies_to', 'RETIRO')->firstOrFail()->id,
                'destination' => 'STOCK',
            ]],
        ])->assertSessionHas('success');
        $this->assertSame('STOCK', $tire->fresh()->status->value);

        $shop = RetreadShop::create([
            'company_id' => $this->admin->company_id,
            'name' => 'Taller E2E',
            'is_active' => true,
        ]);
        $this->post('/ordenes', [
            '_token' => $csrf,
            'tire_id' => $tire->id,
            'retread_shop_id' => $shop->id,
            'type' => 'REPARACION',
            'notes' => 'Flujo crítico',
        ])->assertRedirect();

        $this->assertDatabaseHas('work_orders', [
            'tire_id' => $tire->id,
            'status' => 'ABIERTA',
        ]);
        $this->assertSame(1, WorkOrder::count());
    }

    public function test_open_recap_work_order_with_multiple_tires_from_form(): void
    {
        $this->seedDomain();
        $this->get('/login');
        $csrf = csrf_token();
        $this->post('/login', ['username' => 'admin-test', 'password' => 'password', '_token' => $csrf])
            ->assertRedirect(route('dashboard'));
        $csrf = csrf_token();

        [$a, $b] = app(\App\Services\PurchaseService::class)->confirm(
            app(\App\Services\PurchaseService::class)->create([
                'supplier_id' => Supplier::firstOrFail()->id,
                'base_id' => Base::firstOrFail()->id,
                'purchased_at' => now()->toDateString(),
                'items' => [[
                    'tire_brand_id' => TireModel::with('sizes')->firstOrFail()->tire_brand_id,
                    'tire_model_id' => TireModel::with('sizes')->firstOrFail()->id,
                    'tire_size_id' => TireModel::with('sizes')->firstOrFail()->sizes->first()->id,
                    'quantity' => 2,
                    'first_number' => 88100,
                ]],
            ], $this->admin),
            $this->admin
        )->items->flatMap->tires->values()->all();

        $shop = RetreadShop::create([
            'company_id' => $this->admin->company_id,
            'name' => 'Taller E2E lote',
            'is_active' => true,
        ]);

        $this->post('/ordenes', [
            '_token' => $csrf,
            'tire_ids' => [$a->id, $b->id],
            'retread_shop_id' => $shop->id,
            'type' => 'RECAPADO',
            'notes' => 'Lote de prueba',
        ])->assertRedirect();

        $order = WorkOrder::latest('id')->firstOrFail();
        $this->assertSame(2, $order->items()->count());
    }
}
