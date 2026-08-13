<?php

namespace Tests\Feature;

use App\Enums\IncidentType;
use App\Enums\TireStatus;
use App\Exceptions\DomainException;
use App\Models\MovementReason;
use App\Models\Tire;
use App\Services\CouplingService;
use App\Services\IncidentService;
use App\Services\MeasurementService;
use App\Services\RetirementService;
use App\Services\TireOperationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class TireTraceabilityTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    public function test_installs_available_tire(): void
    {
        $unit = $this->createTractor();
        [$tire] = $this->purchaseTires(1, 50001);
        $position = $unit->configuration->positions()->where('is_spare', false)->first();

        app(TireOperationService::class)->execute($unit, [
            'odometer' => 100000,
            'installations' => [['tire_id' => $tire->id, 'position_id' => $position->id]],
        ], $this->admin);

        $this->assertEquals(TireStatus::Instalada, $tire->fresh()->status);
        $this->assertEquals($unit->id, $tire->fresh()->currentLocation->unit_id);
    }

    public function test_cannot_install_already_installed_tire(): void
    {
        $unitA = $this->createTractor(120000);
        $unitB = $this->createTractor(80000);
        [$tire] = $this->purchaseTires(1, 50010);
        $posA = $unitA->configuration->positions()->where('is_spare', false)->first();
        $posB = $unitB->configuration->positions()->where('is_spare', false)->first();
        $ops = app(TireOperationService::class);
        $ops->execute($unitA, ['odometer' => 120000, 'installations' => [['tire_id' => $tire->id, 'position_id' => $posA->id]]], $this->admin);

        $this->expectException(DomainException::class);
        $ops->execute($unitB, ['odometer' => 80000, 'installations' => [['tire_id' => $tire->id, 'position_id' => $posB->id]]], $this->admin);
    }

    public function test_cannot_install_retired_tire(): void
    {
        $unit = $this->createTractor();
        [$tire] = $this->purchaseTires(1, 50020);
        $reason = MovementReason::where('applies_to', 'BAJA')->first();
        app(RetirementService::class)->retire($tire, ['reason_id' => $reason->id], $this->admin);
        $position = $unit->configuration->positions()->where('is_spare', false)->first();

        $this->expectException(DomainException::class);
        app(TireOperationService::class)->execute($unit, [
            'odometer' => 100000,
            'installations' => [['tire_id' => $tire->id, 'position_id' => $position->id]],
        ], $this->admin);
    }

    public function test_cannot_occupy_taken_position(): void
    {
        $unit = $this->createTractor();
        [$a, $b] = $this->purchaseTires(2, 50030);
        $position = $unit->configuration->positions()->where('is_spare', false)->first();
        $ops = app(TireOperationService::class);
        $ops->execute($unit, ['odometer' => 100000, 'installations' => [['tire_id' => $a->id, 'position_id' => $position->id]]], $this->admin);

        $this->expectException(DomainException::class);
        $ops->execute($unit, ['odometer' => 100000, 'installations' => [['tire_id' => $b->id, 'position_id' => $position->id]]], $this->admin);
    }

    public function test_removal_calculates_kilometers(): void
    {
        $unit = $this->createTractor(120000);
        [$tire] = $this->purchaseTires(1, 50040);
        $position = $unit->configuration->positions()->where('is_spare', false)->first();
        $ops = app(TireOperationService::class);
        $ops->execute($unit, ['odometer' => 120000, 'installations' => [['tire_id' => $tire->id, 'position_id' => $position->id]]], $this->admin);
        $ops->execute($unit, ['odometer' => 135000, 'removals' => [['tire_id' => $tire->id]]], $this->admin);

        $this->assertEquals(15000, $tire->fresh()->accumulated_km);
        $this->assertEquals(TireStatus::Stock, $tire->fresh()->status);
        $this->assertDatabaseHas('tire_movements', ['tire_id' => $tire->id, 'type' => 'REMOVE_TO_STOCK']);
    }

    public function test_cannot_remove_with_lower_odometer(): void
    {
        $unit = $this->createTractor(120000);
        [$tire] = $this->purchaseTires(1, 50050);
        $position = $unit->configuration->positions()->where('is_spare', false)->first();
        $ops = app(TireOperationService::class);
        $ops->execute($unit, ['odometer' => 120000, 'installations' => [['tire_id' => $tire->id, 'position_id' => $position->id]]], $this->admin);

        $this->expectException(DomainException::class);
        $ops->execute($unit, ['odometer' => 119000, 'removals' => [['tire_id' => $tire->id]]], $this->admin);
    }

    public function test_spare_does_not_count_kilometers(): void
    {
        $unit = $this->createTractor(200000);
        [$tire] = $this->purchaseTires(1, 50060);
        $spare = $unit->configuration->positions()->where('is_spare', true)->first();
        $ops = app(TireOperationService::class);
        $ops->execute($unit, ['odometer' => 200000, 'installations' => [['tire_id' => $tire->id, 'position_id' => $spare->id]]], $this->admin);
        $this->assertEquals(TireStatus::Auxilio, $tire->fresh()->status);
        $ops->execute($unit, ['odometer' => 220000, 'removals' => [['tire_id' => $tire->id]]], $this->admin);
        $this->assertEquals(0, $tire->fresh()->accumulated_km);
    }

    public function test_rotation_keeps_kilometers_open(): void
    {
        $unit = $this->createTractor(100000);
        [$tire] = $this->purchaseTires(1, 50070);
        $positions = $unit->configuration->positions()->where('is_spare', false)->orderBy('sort_order')->get();
        $ops = app(TireOperationService::class);
        $ops->execute($unit, ['odometer' => 100000, 'installations' => [['tire_id' => $tire->id, 'position_id' => $positions[0]->id]]], $this->admin);
        $ops->rotate($unit, $tire->id, $positions[1]->id, 110000, $this->admin);
        $ops->execute($unit, ['odometer' => 130000, 'removals' => [['tire_id' => $tire->id]]], $this->admin);

        $this->assertEquals(30000, $tire->fresh()->accumulated_km);
        $this->assertEquals(1, $tire->assignments()->count());
    }

    public function test_unit_change_keeps_full_history_via_stock(): void
    {
        $unitA = $this->createTractor(200000);
        $unitB = $this->createTractor(80000);
        [$tire] = $this->purchaseTires(1, 50080);
        $posA = $unitA->configuration->positions()->where('is_spare', false)->first();
        $posB = $unitB->configuration->positions()->where('is_spare', false)->first();
        $ops = app(TireOperationService::class);
        $ops->execute($unitA, ['odometer' => 200000, 'installations' => [['tire_id' => $tire->id, 'position_id' => $posA->id]]], $this->admin);
        $ops->execute($unitA, ['odometer' => 210000, 'removals' => [['tire_id' => $tire->id]]], $this->admin);
        $ops->execute($unitB, ['odometer' => 80000, 'installations' => [['tire_id' => $tire->id, 'position_id' => $posB->id]]], $this->admin);

        $this->assertEquals(10000, $tire->fresh()->accumulated_km);
        $this->assertEquals($unitB->id, $tire->fresh()->currentLocation->unit_id);
        $this->assertGreaterThanOrEqual(3, $tire->movements()->count());
    }

    public function test_retire_blocks_later_install(): void
    {
        [$tire] = $this->purchaseTires(1, 50090);
        $reason = MovementReason::where('applies_to', 'BAJA')->first();
        app(RetirementService::class)->retire($tire, ['reason_id' => $reason->id, 'notes' => 'Fin de vida'], $this->admin);
        $this->assertEquals(TireStatus::DeBaja, $tire->fresh()->status);
        $this->assertNotNull($tire->fresh()->retired_at);
    }

    public function test_recap_opens_new_life_repair_does_not(): void
    {
        [$tire] = $this->purchaseTires(1, 50100);
        $incidents = app(IncidentService::class);
        $incidents->register($tire, ['type' => IncidentType::Reparacion->value], $this->admin);
        $this->assertEquals(1, $tire->fresh()->lifecycles()->count());
        $incidents->register($tire, ['type' => IncidentType::Recapado->value], $this->admin);
        $this->assertEquals(2, $tire->fresh()->lifecycles()->count());
        $this->assertEquals('RECAPADA', $tire->fresh()->condition->value);
    }

    public function test_trailer_uses_tractor_odometer_and_recouple_splits_segments(): void
    {
        $tractorA = $this->createTractor(100000);
        $tractorB = $this->createTractor(50000);
        $trailer = $this->createTrailer();
        [$tire] = $this->purchaseTires(1, 50110);
        $position = $trailer->configuration->positions()->where('is_spare', false)->first();
        $couplings = app(CouplingService::class);
        $ops = app(TireOperationService::class);

        $couplings->couple($tractorA, $trailer, 100000, $this->admin);
        $ops->execute($trailer, ['odometer' => 100000, 'installations' => [['tire_id' => $tire->id, 'position_id' => $position->id]]], $this->admin);
        $open = $tractorA->currentCouplingAsTractor;
        $couplings->uncouple($open, 112000, $this->admin);
        $couplings->couple($tractorB, $trailer, 50000, $this->admin);
        $ops->execute($trailer, ['odometer' => 58000, 'removals' => [['tire_id' => $tire->id]]], $this->admin);

        $this->assertEquals(20000, $tire->fresh()->accumulated_km);
        $this->assertEquals(2, $tire->assignments()->first()->segments()->count());
    }

    public function test_uneven_wear_raises_alert(): void
    {
        [$tire] = $this->purchaseTires(1, 50120);
        $tire->load('size.zones');
        $readings = $tire->size->zones->map(fn ($zone) => [
            'zone_id' => $zone->id,
            'millimeters' => $zone->code === 'FLANCO_IZQ' ? 14 : 10,
        ])->all();

        $measurement = app(MeasurementService::class)->record($tire, ['readings' => $readings], $this->admin);
        $this->assertTrue($measurement->raises_alert);
        $this->assertEquals(1, $tire->incidents()->where('type', 'DESGASTE_IRREGULAR')->count());
    }

    public function test_display_name_uses_model_and_number(): void
    {
        [$tire] = $this->purchaseTires(1, 30363);
        $this->assertStringContainsString('Nº30363', $tire->fresh()->load('model')->displayName());
    }

    public function test_position_code_uses_flotal_nomenclature(): void
    {
        $tractor = $this->createTractor();
        $steer = $tractor->configuration->positions()->where('axle_number', 1)->where('is_spare', false)->orderBy('sort_order')->get();
        $drive = $tractor->configuration->positions()->where('axle_number', 2)->where('side', 'IZQ')->where('dual', 'EXT')->first();

        $this->assertSame('TC-E1-IZQ', $steer[0]->sheetCode('TC'));
        $this->assertSame('TC-E1-DER', $steer[1]->sheetCode('TC'));
        $this->assertSame('TC-E2-IZQ-EXT', $drive->sheetCode('TC'));
        $this->assertSame('Dirección', $steer[0]->axleRole(true, true));
        $this->assertSame('Tracción', $drive->axleRole(true, false));
        $this->assertSame('Arrastre', $drive->axleRole(false, false));
    }

    public function test_unit_sheet_shows_paper_layout(): void
    {
        $tractor = $this->createTractor();
        $trailer = $this->createTrailer();
        [$tire] = $this->purchaseTires(1, 30363);
        $position = $tractor->configuration->positions()->where('is_spare', false)->first();
        app(CouplingService::class)->couple($tractor, $trailer, 100000, $this->admin);
        app(TireOperationService::class)->execute($tractor, [
            'odometer' => 100000,
            'installations' => [['tire_id' => $tire->id, 'position_id' => $position->id]],
        ], $this->admin);

        $this->get(route('units.show', $tractor))
            ->assertOk()
            ->assertSee('BASE:')
            ->assertSee('FECHA:')
            ->assertSee('TRACTOR')
            ->assertSee('TC-E1-IZQ')
            ->assertSee('Frente')
            ->assertSee($trailer->type->sheetPrefix())
            ->assertSee((string) $tractor->plate)
            ->assertSee((string) $trailer->plate)
            ->assertSee('30363');
    }
}
