<?php

namespace Tests\Feature;

use App\Enums\InventoryLineDelta;
use App\Enums\InventorySessionStatus;
use App\Enums\LocationKind;
use App\Enums\UserRole;
use App\Models\Base;
use App\Models\InventorySession;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class PhysicalInventoryTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    public function test_full_count_marks_missing_and_ok(): void
    {
        $base = Base::first();
        [$a, $b] = $this->purchaseTires(2, 96001);
        $this->assertSame($base->id, $a->fresh()->currentLocation->base_id);

        $service = app(InventoryService::class);
        $session = $service->open($this->admin, $base, 'test');
        $this->assertSame(2, $session->expected_count);
        $this->assertSame(InventorySessionStatus::Open, $session->status);

        $service->startCounting($session, $this->admin);
        $service->scan($session->fresh(), $this->admin, (string) $a->individual_number);
        $service->submitForReview($session->fresh(), $this->admin);

        $session = $session->fresh();
        $this->assertSame(InventorySessionStatus::Review, $session->status);
        $this->assertSame(1, $session->found_count);
        $this->assertSame(1, $session->missing_count);

        $ok = $session->lines()->where('tire_id', $a->id)->first();
        $missing = $session->lines()->where('tire_id', $b->id)->first();
        $this->assertSame(InventoryLineDelta::Ok, $ok->delta);
        $this->assertSame(InventoryLineDelta::Missing, $missing->delta);

        $service->close($session->fresh(), $this->admin, false);
        $this->assertSame(InventorySessionStatus::Closed, $session->fresh()->status);
        $this->assertFalse($session->fresh()->adjustments_applied);
    }

    public function test_wrong_base_can_be_corrected_on_close(): void
    {
        $baseA = Base::first();
        $baseB = Base::create([
            'company_id' => $this->admin->company_id,
            'name' => 'Otra base inv',
            'code' => 'OBI',
            'is_active' => true,
        ]);
        [$tire] = $this->purchaseTires(1, 96100);
        // Sistema dice base B; se cuenta en inventario de A
        $tire->currentLocation->update(['base_id' => $baseB->id]);

        $service = app(InventoryService::class);
        $session = $service->open($this->admin, $baseA);
        $this->assertSame(0, $session->expected_count);

        $service->startCounting($session, $this->admin);
        $line = $service->scan($session->fresh(), $this->admin, (string) $tire->individual_number);
        $this->assertSame(InventoryLineDelta::WrongBase, $line->delta);

        $service->submitForReview($session->fresh(), $this->admin);
        $service->close($session->fresh(), $this->admin, true);

        $this->assertSame($baseA->id, $tire->fresh()->currentLocation->base_id);
        $this->assertDatabaseHas('tire_movements', [
            'tire_id' => $tire->id,
            'type' => 'TRANSFER_BASE',
            'to_base_id' => $baseA->id,
        ]);
        $this->assertTrue($session->fresh()->adjustments_applied);
    }

    public function test_mounted_tire_is_flagged_not_moved(): void
    {
        $base = Base::first();
        [$tire] = $this->purchaseTires(1, 96200);
        $unit = $this->createTractor();
        $position = $unit->configuration->positions()->where('is_spare', false)->first();
        app(\App\Services\TireOperationService::class)->execute($unit, [
            'odometer' => 100000,
            'installations' => [['tire_id' => $tire->id, 'position_id' => $position->id]],
        ], $this->admin);

        $service = app(InventoryService::class);
        $session = $service->open($this->admin, $base);
        $service->startCounting($session, $this->admin);
        $line = $service->scan($session->fresh(), $this->admin, (string) $tire->individual_number);

        $this->assertSame(InventoryLineDelta::Mounted, $line->delta);
        $this->assertSame('INSTALADA', $tire->fresh()->status->value);
    }

    public function test_operario_cannot_apply_location_fixes(): void
    {
        $base = Base::first();
        $this->purchaseTires(1, 96300);
        $operario = User::factory()->create([
            'role' => UserRole::Operario,
            'company_id' => $this->admin->company_id,
        ]);
        $operario->bases()->sync([$base->id]);
        $operario->fleets()->sync($this->admin->fleets()->pluck('fleets.id'));

        $service = app(InventoryService::class);
        $session = $service->open($this->admin, $base);
        $service->startCounting($session, $operario);
        $service->submitForReview($session->fresh(), $operario);

        $this->expectException(\App\Exceptions\DomainException::class);
        $service->close($session->fresh(), $operario, true);
    }

    public function test_inventory_pages_render(): void
    {
        $this->get(route('inventories.index'))->assertOk();
        $this->get(route('inventories.create'))->assertOk();
    }

    public function test_only_one_active_session_per_base(): void
    {
        $base = Base::first();
        $service = app(InventoryService::class);
        $service->open($this->admin, $base);

        $this->expectException(\App\Exceptions\DomainException::class);
        $service->open($this->admin, $base);
    }
}
