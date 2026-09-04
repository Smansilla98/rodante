<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\TireCondition;
use App\Enums\TireStatus;
use App\Enums\UserRole;
use App\Exceptions\DomainException;
use App\Models\Base;
use App\Models\TireMovement;
use App\Models\User;
use App\Services\BaseTransferService;
use App\Services\MovementCorrectionService;
use App\Services\TireOperationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class NonNegotiableRulesTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    public function test_rule_one_tire_one_current_location(): void
    {
        [$tire] = $this->purchaseTires(1, 86001);
        $this->assertSame(1, $tire->currentLocation()->count());
        $this->assertDatabaseCount('tire_current_locations', 1);
    }

    public function test_rule_all_removals_pass_through_stock(): void
    {
        $unit = $this->createTractor();
        [$tire] = $this->purchaseTires(1, 86010, 'FH:01');
        $steer = $unit->configuration->positions()->where('axle_number', 1)->where('is_spare', false)->first();
        $ops = app(TireOperationService::class);
        $ops->execute($unit, [
            'odometer' => 100000,
            'installations' => [['tire_id' => $tire->id, 'position_id' => $steer->id]],
        ], $this->admin);

        $ops->execute($unit, [
            'odometer' => 101000,
            'removals' => [['tire_id' => $tire->id]],
        ], $this->admin);

        $tire = $tire->fresh();
        $this->assertSame(TireStatus::Stock, $tire->status);
        $this->assertDatabaseHas('tire_movements', [
            'tire_id' => $tire->id,
            'type' => MovementType::RemoveToStock->value,
        ]);
        $this->assertNull($tire->currentLocation?->unit_id);
    }

    public function test_rule_recap_opens_new_life_repair_does_not(): void
    {
        [$repair] = $this->purchaseTires(1, 86020);
        $repair->update(['status' => TireStatus::EnReparacion]);
        $lifeBefore = (int) ($repair->currentLifecycle?->life_number ?? 1);
        app(TireOperationService::class)->returnToStock($repair, $this->admin, 'parche', false);
        $repair->refresh();
        $this->assertSame(TireStatus::Stock, $repair->status);
        $this->assertSame(TireCondition::Reparada, $repair->condition);
        $this->assertSame($lifeBefore, (int) $repair->currentLifecycle?->life_number);
        $this->assertDatabaseHas('tire_movements', [
            'tire_id' => $repair->id,
            'type' => MovementType::FromRepair->value,
        ]);

        [$recap] = $this->purchaseTires(1, 86021);
        $recap->update(['status' => TireStatus::EnReparacion]);
        $before = $recap->lifecycles()->count();
        app(TireOperationService::class)->returnToStock($recap, $this->admin, 'recap', true);
        $recap->refresh();
        $this->assertSame(TireCondition::Recapada, $recap->condition);
        $this->assertSame($before + 1, $recap->lifecycles()->count());
        $this->assertSame('RECAPADO', $recap->currentLifecycle?->started_by);
    }

    public function test_rule_spare_does_not_count_kilometers(): void
    {
        $unit = $this->createTractor();
        [$service, $spare] = $this->purchaseTires(2, 86030, 'FH:01');
        $steer = $unit->configuration->positions()->where('axle_number', 1)->where('is_spare', false)->first();
        $aux = $unit->configuration->positions()->where('is_spare', true)->first();
        $this->assertNotNull($aux);
        $ops = app(TireOperationService::class);
        $ops->execute($unit, [
            'odometer' => 200000,
            'installations' => [
                ['tire_id' => $service->id, 'position_id' => $steer->id],
                ['tire_id' => $spare->id, 'position_id' => $aux->id],
            ],
        ], $this->admin);

        $ops->execute($unit, [
            'odometer' => 205000,
            'removals' => [
                ['tire_id' => $service->id, 'position_id' => $steer->id],
                ['tire_id' => $spare->id, 'position_id' => $aux->id],
            ],
        ], $this->admin);

        $this->assertGreaterThan(0, (int) $service->fresh()->accumulated_km);
        $this->assertSame(0, (int) $spare->fresh()->accumulated_km);
    }

    public function test_rule_history_events_are_immutable(): void
    {
        [$tire] = $this->purchaseTires(1, 86040);
        $movement = $tire->movements()->first();
        $this->assertNotNull($movement);
        $originalNotes = $movement->notes;
        $before = TireMovement::count();

        app(MovementCorrectionService::class)->record($tire->fresh(), 'Corrección de prueba', $this->admin);

        $movement->refresh();
        $this->assertSame($originalNotes, $movement->notes);
        $this->assertSame($before + 1, TireMovement::count());
        $this->assertTrue($tire->fresh()->movements()->where('type', MovementType::Correction->value)->exists());
    }

    public function test_rule_transfer_rejects_installed_tire(): void
    {
        $unit = $this->createTractor();
        [$tire] = $this->purchaseTires(1, 86050, 'FH:01');
        $steer = $unit->configuration->positions()->where('axle_number', 1)->where('is_spare', false)->first();
        app(TireOperationService::class)->execute($unit, [
            'odometer' => 110000,
            'installations' => [['tire_id' => $tire->id, 'position_id' => $steer->id]],
        ], $this->admin);

        $toBase = Base::create([
            'company_id' => $this->admin->company_id,
            'name' => 'Base norte',
            'code' => 'NORTE-R',
            'is_active' => true,
        ]);

        $this->expectException(DomainException::class);
        app(BaseTransferService::class)->transfer($tire->fresh(), $toBase, $this->admin);
    }

    public function test_rule_consulta_can_export_csv(): void
    {
        $this->purchaseTires(1, 86060);
        $consulta = User::factory()->create([
            'role' => UserRole::Consulta,
            'company_id' => $this->admin->company_id,
        ]);

        $this->actingAs($consulta)
            ->get(route('exports.tires'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($consulta)->get(route('measurements.index'))->assertOk();
        $this->actingAs($consulta)->get(route('incidents.index'))->assertOk();
        $this->actingAs($consulta)->get(route('couplings.index'))->assertOk();
    }

    public function test_rule_reserve_return_records_from_reserva(): void
    {
        [$tire] = $this->purchaseTires(1, 86070);
        $tire->update(['status' => TireStatus::Reserva]);
        app(TireOperationService::class)->returnToStock($tire, $this->admin, 'sale de reserva');
        $this->assertDatabaseHas('tire_movements', [
            'tire_id' => $tire->id,
            'type' => MovementType::FromReserva->value,
        ]);
        $this->assertSame(TireStatus::Stock, $tire->fresh()->status);
    }
}
