<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\TireCondition;
use App\Enums\TireStatus;
use App\Enums\UserRole;
use App\Enums\WorkOrderType;
use App\Exceptions\DomainException;
use App\Models\Base;
use App\Models\Company;
use App\Models\RetreadShop;
use App\Models\Supplier;
use App\Models\Tire;
use App\Models\TireAssignment;
use App\Models\TireModel;
use App\Models\TireSize;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Services\MovementCorrectionService;
use App\Services\PurchaseService;
use App\Services\TireIdentityService;
use App\Services\WorkOrderService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class ProductionHardeningTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    public function test_admin_of_other_company_cannot_see_tires(): void
    {
        [$tire] = $this->purchaseTires(1, 88001);
        $other = Company::create(['name' => 'Otra', 'slug' => 'otra', 'is_active' => true]);
        $intruder = User::factory()->create([
            'username' => 'intruso',
            'role' => UserRole::Administrador,
            'company_id' => $other->id,
        ]);

        $this->actingAs($intruder)
            ->get(route('tires.show', $tire))
            ->assertNotFound();
    }

    public function test_tire_movements_are_immutable(): void
    {
        [$tire] = $this->purchaseTires(1, 88010);
        $movement = $tire->movements()->first();
        $this->assertNotNull($movement);

        $this->expectException(DomainException::class);
        $movement->update(['notes' => 'hack']);
    }

    /** Cubre INC-07: el registro de auditoría ahora está protegido igual que tire_movements. */
    public function test_audit_logs_are_immutable(): void
    {
        [$tire] = $this->purchaseTires(1, 88012);
        $log = \App\Models\AuditLog::where('entity_type', Tire::class)->orWhere('action', 'purchase.confirmed')->first();
        $this->assertNotNull($log);

        $this->expectException(DomainException::class);
        $log->update(['action' => 'hack']);
    }

    public function test_correction_creates_new_movement(): void
    {
        [$tire] = $this->purchaseTires(1, 88011);
        $before = $tire->movements()->count();
        app(MovementCorrectionService::class)->record($tire->fresh(), 'Ajuste de nota de plaza', $this->admin);
        $this->assertSame($before + 1, $tire->fresh()->movements()->count());
        $this->assertTrue(
            $tire->fresh()->movements()->where('type', 'CORRECTION')->exists()
        );
    }

    public function test_individual_number_change_keeps_history(): void
    {
        [$tire] = $this->purchaseTires(1, 88020);
        $from = $tire->individual_number;
        app(TireIdentityService::class)->changeNumber($tire, 99001, 'Error de carga', $this->admin);
        $tire->refresh();
        $this->assertSame(99001, (int) $tire->individual_number);
        $this->assertDatabaseHas('tire_number_changes', [
            'tire_id' => $tire->id,
            'from_number' => $from,
            'to_number' => 99001,
            'user_id' => $this->admin->id,
        ]);
        $this->assertTrue($tire->movements()->exists());
    }

    public function test_tire_dot_is_saved_normalized_for_warranty(): void
    {
        [$tire] = $this->purchaseTires(1, 88110);
        $this->actingAs($this->admin)
            ->withSession(['_token' => 'test-csrf-token'])
            ->put(route('tires.update', $tire), [
                '_token' => 'test-csrf-token',
                'individual_number' => $tire->individual_number,
                'dot' => '1b3c 4d-0524',
                'tire_brand_id' => $tire->tire_brand_id,
                'tire_model_id' => $tire->tire_model_id,
                'tire_size_id' => $tire->tire_size_id,
                'condition' => $tire->condition->value,
            ])
            ->assertRedirect(route('tires.show', $tire));

        $tire->refresh();
        $this->assertSame('1B3C4D0524', $tire->dot);
        $this->assertSame(['week' => 5, 'year' => 2024], $tire->manufactureWeekYear());
        $this->assertSame('Semana 5 / 2024', $tire->manufactureLabel());

        [$other] = $this->purchaseTires(1, 88111);
        $this->actingAs($this->admin)
            ->withSession(['_token' => 'test-csrf-token'])
            ->from(route('tires.show', [$other, 'edit' => 1]))
            ->put(route('tires.update', $other), [
                '_token' => 'test-csrf-token',
                'individual_number' => $other->individual_number,
                'dot' => '1B3C4D0524',
                'tire_brand_id' => $other->tire_brand_id,
                'tire_model_id' => $other->tire_model_id,
                'tire_size_id' => $other->tire_size_id,
                'condition' => $other->condition->value,
            ])
            ->assertSessionHasErrors('dot');
    }

    public function test_purchase_qty_one_carries_dot_into_tire(): void
    {
        $model = TireModel::with('brand', 'sizes')->firstOrFail();
        $size = $model->sizes->first() ?: TireSize::firstOrFail();
        $purchase = app(PurchaseService::class)->create([
            'supplier_id' => Supplier::firstOrFail()->id,
            'base_id' => Base::firstOrFail()->id,
            'purchased_at' => now()->toDateString(),
            'items' => [[
                'tire_brand_id' => $model->tire_brand_id,
                'tire_model_id' => $model->id,
                'tire_size_id' => $size->id,
                'quantity' => 1,
                'first_number' => 88120,
                'dot' => 'xy9876 1223',
            ]],
        ], $this->admin);
        app(PurchaseService::class)->confirm($purchase, $this->admin);
        $tire = Tire::where('individual_number', 88120)->firstOrFail();
        $this->assertSame('XY98761223', $tire->dot);
        $this->assertSame(['week' => 12, 'year' => 2023], $tire->manufactureWeekYear());
    }

    public function test_purchase_numbers_are_sequential_per_company(): void
    {
        $numbers = app(DocumentNumberService::class);
        $companyId = (int) $this->admin->company_id;
        $a = $numbers->next($companyId, 'hardening_purchase', 'OC-');
        $b = $numbers->next($companyId, 'hardening_purchase', 'OC-');
        $this->assertSame('OC-00001', $a);
        $this->assertSame('OC-00002', $b);
    }

    public function test_only_one_open_assignment_per_tire(): void
    {
        [$tire] = $this->purchaseTires(1, 88030);
        $unit = $this->createTractor();
        $positions = $unit->configuration->positions()->where('is_spare', false)->orderBy('id')->get();
        TireAssignment::create([
            'tire_id' => $tire->id,
            'tire_lifecycle_id' => $tire->current_lifecycle_id,
            'unit_id' => $unit->id,
            'start_position_id' => $positions[0]->id,
            'started_at' => now(),
        ]);
        $this->expectException(QueryException::class);
        TireAssignment::create([
            'tire_id' => $tire->id,
            'tire_lifecycle_id' => $tire->current_lifecycle_id,
            'unit_id' => $unit->id,
            'start_position_id' => $positions[1]->id,
            'started_at' => now(),
        ]);
    }

    public function test_field_api_issues_and_revokes_token(): void
    {
        auth()->logout();
        $token = $this->postJson('/api/v1/auth/token', [
            'username' => $this->admin->username,
            'password' => 'password',
            'device' => 'tablet',
        ])->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/v1/tires')->assertOk();
        $this->withToken($token)->deleteJson('/api/v1/auth/token')->assertOk();
    }

    public function test_work_order_repair_does_not_open_new_life(): void
    {
        [$tire] = $this->purchaseTires(1, 88040);
        $shop = RetreadShop::create([
            'company_id' => $this->admin->company_id,
            'name' => 'Taller test',
            'is_active' => true,
        ]);
        $service = app(WorkOrderService::class);
        $order = $service->open($this->admin, $tire, $shop, WorkOrderType::Reparacion, 'parche');
        $service->sendToShop($order->fresh(), $this->admin);
        $service->close($order->fresh(), $this->admin, 1500, 'listo');
        $this->assertSame(1, $tire->fresh()->lifecycles()->count());
        $this->assertDatabaseHas('cost_entries', [
            'company_id' => $this->admin->company_id,
            'category' => 'REPAIR',
        ]);
    }

    /** Sin recapadora externa: orden interna, hecha por personal propio en el taller de la empresa. */
    public function test_work_order_can_be_internal_without_a_shop(): void
    {
        [$tire] = $this->purchaseTires(1, 88045);
        $service = app(WorkOrderService::class);

        $order = $service->open($this->admin, $tire, null, WorkOrderType::Reparacion, 'Gomero interno, parche');
        $this->assertNull($order->retread_shop_id);
        $this->assertTrue($order->isInternal());
        $this->assertSame('Taller interno', $order->shopLabel());

        $service->sendToShop($order->fresh(), $this->admin);
        $service->close($order->fresh(), $this->admin, 500, 'listo');

        $this->assertSame(1, $tire->fresh()->lifecycles()->count());
        $this->assertSame('REPARADA', $tire->fresh()->condition->value);
        $this->assertDatabaseHas('cost_entries', [
            'company_id' => $this->admin->company_id,
            'category' => 'REPAIR',
        ]);
        $this->assertDatabaseHas('work_orders', [
            'id' => $order->id,
            'retread_shop_id' => null,
            'status' => 'CERRADA',
        ]);
    }

    public function test_work_order_recap_can_group_multiple_tires(): void
    {
        [$a, $b] = $this->purchaseTires(2, 88060);
        $shop = RetreadShop::create([
            'company_id' => $this->admin->company_id,
            'name' => 'Taller lote',
            'is_active' => true,
        ]);

        $order = app(WorkOrderService::class)->open(
            $this->admin,
            collect([$a, $b]),
            $shop,
            WorkOrderType::Recapado,
            'Lote de recapado'
        );

        $this->assertSame(2, $order->items()->count());
        $this->assertDatabaseHas('work_order_items', ['work_order_id' => $order->id, 'tire_id' => $a->id]);
        $this->assertDatabaseHas('work_order_items', ['work_order_id' => $order->id, 'tire_id' => $b->id]);
    }

    /**
     * Cubre INC-02 (docs/AUDIT_OT_STOCK_RECAPADO.md): cerrar una OT de tipo
     * Recapado no tenía ningún test. Verifica que deje exactamente el mismo
     * rastro que el retorno directo a stock (vía B): vida nueva, condición
     * RECAPADA, status de vuelta a STOCK y un tire_movements tipo FROM_REPAIR.
     */
    public function test_work_order_recap_close_opens_new_life_and_returns_to_stock(): void
    {
        [$tire] = $this->purchaseTires(1, 88070);
        $shop = RetreadShop::create([
            'company_id' => $this->admin->company_id,
            'name' => 'Taller recap',
            'is_active' => true,
        ]);
        $lifeBefore = (int) ($tire->currentLifecycle?->life_number ?? 1);

        $service = app(WorkOrderService::class);
        $order = $service->open($this->admin, $tire, $shop, WorkOrderType::Recapado, 'Recapado de prueba');
        $service->sendToShop($order->fresh(), $this->admin);
        $this->assertSame(TireStatus::EnReparacion, $tire->fresh()->status);

        $closed = $service->close($order->fresh(), $this->admin, 2000, 'Listo');

        $tire->refresh();
        $this->assertSame(TireStatus::Stock, $tire->status);
        $this->assertSame(TireCondition::Recapada, $tire->condition);
        $this->assertSame($lifeBefore + 1, (int) $tire->currentLifecycle?->life_number);
        $this->assertSame('RECAPADO', $tire->currentLifecycle?->started_by);
        $this->assertDatabaseHas('tire_movements', [
            'tire_id' => $tire->id,
            'type' => MovementType::FromRepair->value,
        ]);
        $this->assertDatabaseHas('cost_entries', [
            'company_id' => $this->admin->company_id,
            'category' => 'RECAP',
        ]);
        $this->assertSame('CERRADA', $closed->status->value);
    }

    /** Mismo caso que arriba pero con un lote de 2 cubiertas: cada una debe abrir su propia vida y su propio movimiento. */
    public function test_work_order_recap_close_with_multiple_tires_opens_a_life_and_movement_per_tire(): void
    {
        [$a, $b] = $this->purchaseTires(2, 88090);
        $shop = RetreadShop::create([
            'company_id' => $this->admin->company_id,
            'name' => 'Taller recap lote',
            'is_active' => true,
        ]);

        $service = app(WorkOrderService::class);
        $order = $service->open($this->admin, collect([$a, $b]), $shop, WorkOrderType::Recapado, 'Lote');
        $service->sendToShop($order->fresh(), $this->admin);
        $service->close($order->fresh(), $this->admin, 1000, 'Listo lote');

        foreach ([$a, $b] as $tire) {
            $tire->refresh();
            $this->assertSame(TireStatus::Stock, $tire->status);
            $this->assertSame(TireCondition::Recapada, $tire->condition);
            $this->assertDatabaseHas('tire_movements', [
                'tire_id' => $tire->id,
                'type' => MovementType::FromRepair->value,
            ]);
        }
    }

    /**
     * Cubre INC-08: cerrar/cancelar una OT de Recapado debe exigir el mismo
     * permiso que abrirla (canRetireOrRecap), no solo canWrite().
     */
    public function test_operario_cannot_close_recap_work_order_that_admin_opened(): void
    {
        [$tire] = $this->purchaseTires(1, 88095);
        $shop = RetreadShop::create([
            'company_id' => $this->admin->company_id,
            'name' => 'Taller permisos',
            'is_active' => true,
        ]);
        $operario = User::factory()->create([
            'role' => UserRole::Operario,
            'company_id' => $this->admin->company_id,
        ]);
        $operario->fleets()->sync($this->admin->fleets()->pluck('fleets.id'));
        $operario->bases()->sync($this->admin->bases()->pluck('bases.id'));

        $service = app(WorkOrderService::class);
        $order = $service->open($this->admin, $tire, $shop, WorkOrderType::Recapado, 'Permisos');
        $service->sendToShop($order->fresh(), $this->admin);

        $this->assertFalse(Gate::forUser($operario)->allows('manage', $order->fresh()));

        // Una OT de reparación sigue permitiendo el cierre con solo canWrite().
        [$repairTire] = $this->purchaseTires(1, 88096);
        $repairOrder = $service->open($this->admin, $repairTire, $shop, WorkOrderType::Reparacion, 'Parche');
        $this->assertTrue(Gate::forUser($operario)->allows('manage', $repairOrder->fresh()));
    }

    public function test_work_order_repair_rejects_multiple_tires(): void
    {
        [$a, $b] = $this->purchaseTires(2, 88080);
        $shop = RetreadShop::create([
            'company_id' => $this->admin->company_id,
            'name' => 'Taller unitario',
            'is_active' => true,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('La reparación es de una sola cubierta');

        app(WorkOrderService::class)->open(
            $this->admin,
            collect([$a, $b]),
            $shop,
            WorkOrderType::Reparacion,
            'No debe permitir lote'
        );
    }

    public function test_password_requires_letters_and_numbers(): void
    {
        $this->get(route('users.index'))->assertOk();
        $this->post(route('users.store'), [
            '_token' => csrf_token(),
            'name' => 'Corto',
            'username' => 'corto8',
            'password' => '12345678',
            'role' => UserRole::Operario->value,
        ])->assertSessionHasErrors('password');
    }

    public function test_qr_does_not_leak_other_company(): void
    {
        [$tire] = $this->purchaseTires(1, 88050);
        $other = Company::create(['name' => 'Otra QR', 'slug' => 'otra-qr', 'is_active' => true]);
        $intruder = User::factory()->create([
            'role' => UserRole::Administrador,
            'company_id' => $other->id,
        ]);
        $this->actingAs($intruder)
            ->get(route('qr.resolve', $tire->public_token))
            ->assertNotFound();
    }
}
