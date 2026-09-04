<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\TireStatus;
use App\Exceptions\DomainException;
use App\Models\Base;
use App\Services\BaseTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class BaseTransferServiceTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    public function test_transfers_stock_tire_and_records_movement_and_audit(): void
    {
        [$tire] = $this->purchaseTires(1, 97001);
        $fromBaseId = $tire->currentLocation()->value('base_id');
        $toBase = Base::create([
            'company_id' => $this->admin->company_id,
            'name' => 'Base destino',
            'code' => 'DEST-BASE',
            'is_active' => true,
        ]);

        app(BaseTransferService::class)->transfer($tire, $toBase, $this->admin, 'Prueba');

        $this->assertDatabaseHas('tire_current_locations', [
            'tire_id' => $tire->id,
            'base_id' => $toBase->id,
        ]);
        $this->assertDatabaseHas('tire_movements', [
            'tire_id' => $tire->id,
            'type' => MovementType::TransferBase->value,
            'from_base_id' => $fromBaseId,
            'to_base_id' => $toBase->id,
            'notes' => 'Prueba',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tire.base_transferred',
            'entity_id' => $tire->id,
        ]);
    }

    public function test_rejects_transfer_for_installed_tire(): void
    {
        $unit = $this->createTractor();
        [$tire] = $this->purchaseTires(1, 97003, 'FH:01');
        $steer = $unit->configuration->positions()->where('axle_number', 1)->where('is_spare', false)->first();
        app(\App\Services\TireOperationService::class)->execute($unit, [
            'odometer' => 150000,
            'installations' => [['tire_id' => $tire->id, 'position_id' => $steer->id]],
        ], $this->admin);

        $this->expectException(DomainException::class);
        app(BaseTransferService::class)->transfer($tire->fresh(), Base::firstOrFail(), $this->admin);
    }

    public function test_rejects_transfer_for_non_stockable_status(): void
    {
        [$tire] = $this->purchaseTires(1, 97002);
        $tire->update(['status' => TireStatus::DeBaja]);

        $this->expectException(DomainException::class);
        app(BaseTransferService::class)->transfer($tire, Base::firstOrFail(), $this->admin);
    }
}
