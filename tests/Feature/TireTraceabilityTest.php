<?php

namespace Tests\Feature;

use App\Enums\IncidentType;
use App\Enums\TireStatus;
use App\Enums\UserRole;
use App\Exceptions\DomainException;
use App\Models\Base;
use App\Models\FleetUnit;
use App\Models\MovementReason;
use App\Models\Supplier;
use App\Models\TireBrand;
use App\Models\TireCurrentLocation;
use App\Models\TireModel;
use App\Models\TireSize;
use App\Models\UnitConfiguration;
use App\Models\UnitType;
use App\Models\User;
use App\Services\CouplingService;
use App\Services\IncidentService;
use App\Services\MeasurementService;
use App\Services\PositionFitService;
use App\Services\PurchaseService;
use App\Services\RetirementService;
use App\Services\RotationPatternService;
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

    public function test_rotation_to_spare_stops_counting_kilometers_without_closing_assignment(): void
    {
        $unit = $this->createTractor(100000);
        [$tire] = $this->purchaseTires(1, 50071);
        $rolling = $unit->configuration->positions()->where('is_spare', false)->orderBy('sort_order')->first();
        $spare = $unit->configuration->positions()->where('is_spare', true)->first();
        $ops = app(TireOperationService::class);

        $ops->execute($unit, ['odometer' => 100000, 'installations' => [['tire_id' => $tire->id, 'position_id' => $rolling->id]]], $this->admin);
        $ops->rotate($unit, $tire->id, $spare->id, 110000, $this->admin);

        $this->assertEquals(TireStatus::Auxilio, $tire->fresh()->status);
        $this->assertEquals(1, $tire->assignments()->whereNull('ended_at')->count());
        $this->assertEquals(10000, $tire->fresh()->accumulated_km);

        $ops->execute($unit, ['odometer' => 130000, 'removals' => [['tire_id' => $tire->id]]], $this->admin);

        $this->assertEquals(10000, $tire->fresh()->accumulated_km);
        $this->assertEquals(1, $tire->assignments()->count());
        $this->assertEquals(2, $tire->assignments()->first()->segments()->count());
    }

    public function test_mounted_tire_has_single_current_location_and_open_assignment(): void
    {
        $unit = $this->createTractor();
        [$tire] = $this->purchaseTires(1, 50072);
        $position = $unit->configuration->positions()->where('is_spare', false)->first();
        app(TireOperationService::class)->execute($unit, [
            'odometer' => 100000,
            'installations' => [['tire_id' => $tire->id, 'position_id' => $position->id]],
        ], $this->admin);

        $this->assertEquals(1, TireCurrentLocation::where('tire_id', $tire->id)->count());
        $this->assertEquals(1, $tire->assignments()->whereNull('ended_at')->count());
    }

    public function test_operario_cannot_register_recap(): void
    {
        [$tire] = $this->purchaseTires(1, 50073);
        $operario = User::factory()->create(['role' => UserRole::Operario]);

        $this->expectException(DomainException::class);
        app(IncidentService::class)->register($tire, ['type' => IncidentType::Recapado->value], $operario);
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
        $this->assertEquals('REPARADA', $tire->fresh()->condition->value);
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
        $this->assertSame('Dirección', $steer[0]->axleRole());
        $this->assertSame('Tracción', $drive->axleRole());
        $this->assertSame('Eje 1 — Delantero izquierdo', $steer[0]->name);

        $trailer = $this->createTrailer();
        $drag = $trailer->configuration->positions()->where('is_spare', false)->first();
        $this->assertSame('Arrastre', $drag->axleRole());
        $this->assertSame('SR-E1-IZQ-EXT', $drag->sheetCode('SR'));

        $sixByTwo = UnitConfiguration::where('code', '6X2')->first();
        $driveSixTwo = $sixByTwo->positions()->where('axle_number', 2)->where('dual', 'EXT')->first();
        $tag = $sixByTwo->positions()->where('axle_number', 3)->where('is_spare', false)->first();
        $this->assertSame('Tracción', $driveSixTwo->axleRole());
        $this->assertStringContainsString('Arrastre', $tag->axleRole());
        $this->assertTrue($tag->is_liftable);

        $pusher = UnitConfiguration::where('code', '6X2-P')->first();
        $pusherTag = $pusher->positions()->where('axle_number', 2)->where('is_spare', false)->first();
        $pusherDrive = $pusher->positions()->where('axle_number', 3)->where('dual', 'EXT')->first();
        $this->assertStringContainsString('Arrastre', $pusherTag->axleRole());
        $this->assertTrue($pusherTag->is_liftable);
        $this->assertSame('Tracción', $pusherDrive->axleRole());
    }

    public function test_real_wheel_formulas_and_trailer_axles_are_catalogued(): void
    {
        $this->assertTrue(UnitType::where('code', 'TRACTOR')->exists());
        $this->assertTrue(UnitType::where('code', 'CAMION')->exists());
        $this->assertEqualsCanonicalizing(
            ['4X2', '4X4', '6X2', '6X2-P', '6X4', '6X6', '8X2', '8X4', '8X8', '10X4', '10X6'],
            UnitConfiguration::where('applies_to', 'POWERED')->pluck('code')->all()
        );

        $sixByFour = UnitConfiguration::where('code', '6X4')->first();
        $this->assertSame(3, $sixByFour->axle_count);
        $this->assertSame(2, $sixByFour->drive_axle_count);
        $this->assertSame(11, $sixByFour->position_count);
        $this->assertTrue($sixByFour->isCompatibleWith(UnitType::where('code', 'TRACTOR')->first()));
        $this->assertFalse($sixByFour->isCompatibleWith(UnitType::where('code', 'TANQUE')->first()));

        $threeDual = UnitConfiguration::where('code', '3E-D')->first();
        $this->assertSame(3, $threeDual->axle_count);
        $this->assertSame(13, $threeDual->position_count);
        $this->assertTrue($threeDual->isCompatibleWith(UnitType::where('code', 'TANQUE')->first()));
        $this->assertFalse($threeDual->isCompatibleWith(UnitType::where('code', 'TRACTOR')->first()));

        $oneAxle = UnitConfiguration::where('code', '1E-S')->first();
        $this->assertTrue($oneAxle->isCompatibleWith(UnitType::where('code', 'SEMIRREMOLQUE')->first()));
        $this->assertFalse($oneAxle->isCompatibleWith(UnitType::where('code', 'TANQUE')->first()));

        $threeLineal = UnitConfiguration::where('code', '3E-S')->first();
        $this->assertSame(7, $threeLineal->position_count);
        $this->assertStringContainsString('lineal', strtolower($threeLineal->name));
        $this->assertTrue($threeLineal->isCompatibleWith(UnitType::where('code', 'TANQUE')->first()));
        $this->assertSame(0, $threeLineal->positions()->where('is_spare', false)->whereNotNull('dual')->count());
        $this->assertTrue(TireSize::where('code', '385/65 R22.5')->exists());
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
            ->assertSee('Base')
            ->assertSee('Fecha')
            ->assertSee('TRACTOR')
            ->assertSee('TC-E1-IZQ')
            ->assertSee('Dirección')
            ->assertSee($trailer->type->sheetPrefix())
            ->assertSee((string) $tractor->plate)
            ->assertSee((string) $trailer->plate)
            ->assertSee('30363');
    }

    public function test_steer_tire_cannot_install_on_drive_axle(): void
    {
        $unit = $this->createTractor();
        [$tire] = $this->purchaseTires(1, 50200, 'FH:01');
        $drive = $unit->configuration->positions()
            ->where('axle_number', 2)
            ->where('axle_role', 'TRACCION')
            ->where('is_spare', false)
            ->first();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no puede instalarse en tracción');

        app(TireOperationService::class)->execute($unit, [
            'odometer' => 100000,
            'installations' => [['tire_id' => $tire->id, 'position_id' => $drive->id]],
        ], $this->admin);
    }

    public function test_steer_tire_can_install_on_third_drive_axle(): void
    {
        $unit = $this->createTractor();
        [$tire] = $this->purchaseTires(1, 50210, 'FH:01');
        $third = $unit->configuration->positions()
            ->where('axle_number', 3)
            ->where('axle_role', 'TRACCION')
            ->where('is_spare', false)
            ->first();

        app(TireOperationService::class)->execute($unit, [
            'odometer' => 100000,
            'installations' => [['tire_id' => $tire->id, 'position_id' => $third->id]],
        ], $this->admin);

        $this->assertEquals(TireStatus::Instalada, $tire->fresh()->status);
        $this->assertEquals($third->id, $tire->fresh()->currentLocation->position_id);
    }

    public function test_recapped_steer_tire_can_install_on_drive_axle(): void
    {
        $unit = $this->createTractor();
        [$tire] = $this->purchaseTires(1, 50220, 'FH:01');
        app(IncidentService::class)->register($tire, ['type' => IncidentType::Recapado->value], $this->admin);

        $drive = $unit->configuration->positions()
            ->where('axle_number', 2)
            ->where('axle_role', 'TRACCION')
            ->where('is_spare', false)
            ->first();

        app(TireOperationService::class)->execute($unit, [
            'odometer' => 100000,
            'installations' => [['tire_id' => $tire->fresh()->id, 'position_id' => $drive->id]],
        ], $this->admin);

        $this->assertEquals(TireStatus::Instalada, $tire->fresh()->status);
    }

    public function test_drive_tire_cannot_install_on_steer_axle(): void
    {
        $unit = $this->createTractor();
        [$tire] = $this->purchaseTires(1, 50225, 'TR:01');
        $steer = $unit->configuration->positions()
            ->where('axle_number', 1)
            ->where('axle_role', 'DIRECCION')
            ->where('is_spare', false)
            ->first();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no puede instalarse en dirección');

        app(TireOperationService::class)->execute($unit, [
            'odometer' => 100000,
            'installations' => [['tire_id' => $tire->id, 'position_id' => $steer->id]],
        ], $this->admin);
    }

    public function test_install_form_hides_drive_tires_from_steer_dropdown(): void
    {
        $unit = $this->createTractor();
        [$steerTire] = $this->purchaseTires(1, 50226, 'FH:01');
        [$driveTire] = $this->purchaseTires(1, 50227, 'TR:01');
        $steer = $unit->configuration->positions()
            ->where('axle_number', 1)
            ->where('axle_role', 'DIRECCION')
            ->where('is_spare', false)
            ->first();

        $html = $this->get(route('units.show', $unit))->assertOk()->getContent();
        $this->assertNotNull($steer);
        $this->assertStringNotContainsString('Disponibles', $html);
        $this->assertStringNotContainsString('FH:01 Nº50226', $html);
        $this->assertStringNotContainsString('TR:01 Nº50227', $html);

        $json = $this->getJson(route('units.stock', ['unit' => $unit, 'position_id' => $steer->id]))
            ->assertOk()
            ->json();
        $ids = collect($json['data'] ?? [])->pluck('id');

        $this->assertTrue($ids->contains($steerTire->id));
        $this->assertFalse($ids->contains($driveTire->id));
        $this->assertSame('DIRECCION', $json['application']);
    }

    public function test_drive_tire_can_install_on_drive_axle(): void
    {
        $unit = $this->createTractor();
        [$tire] = $this->purchaseTires(1, 50230, 'TR:01');
        $drive = $unit->configuration->positions()
            ->where('axle_number', 2)
            ->where('axle_role', 'TRACCION')
            ->where('is_spare', false)
            ->first();

        app(TireOperationService::class)->execute($unit, [
            'odometer' => 100000,
            'installations' => [['tire_id' => $tire->id, 'position_id' => $drive->id]],
        ], $this->admin);

        $this->assertEquals(TireStatus::Instalada, $tire->fresh()->status);
    }

    public function test_steer_tire_cannot_rotate_onto_drive_axle(): void
    {
        $unit = $this->createTractor();
        [$tire] = $this->purchaseTires(1, 50240, 'FH:01');
        $steer = $unit->configuration->positions()->where('axle_number', 1)->where('is_spare', false)->first();
        $drive = $unit->configuration->positions()
            ->where('axle_number', 2)
            ->where('axle_role', 'TRACCION')
            ->where('is_spare', false)
            ->first();
        $ops = app(TireOperationService::class);
        $ops->execute($unit, [
            'odometer' => 100000,
            'installations' => [['tire_id' => $tire->id, 'position_id' => $steer->id]],
        ], $this->admin);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no puede instalarse en tracción');

        $ops->rotate($unit, $tire->id, $drive->id, 110000, $this->admin);
    }

    public function test_map_cambio_replaces_tire_and_logs_incident(): void
    {
        $unit = $this->createTractor();
        [$old] = $this->purchaseTires(1, 50300, 'FH:01');
        [$nuevo] = $this->purchaseTires(1, 50301, 'FH:01');
        $steer = $unit->configuration->positions()->where('axle_number', 1)->where('is_spare', false)->first();
        $ops = app(TireOperationService::class);
        $ops->execute($unit, [
            'odometer' => 100000,
            'installations' => [['tire_id' => $old->id, 'position_id' => $steer->id]],
        ], $this->admin);

        app(IncidentService::class)->register($old, [
            'type' => IncidentType::Cambio->value,
            'odometer' => 101000,
            'unit_id' => $unit->id,
            'position_id' => $steer->id,
        ], $this->admin);
        $ops->execute($unit, [
            'odometer' => 101000,
            'notes' => 'Cambio',
            'removals' => [[
                'tire_id' => $old->id,
                'reason_id' => MovementReason::where('code', 'RECAMBIO')->value('id'),
                'destination' => TireStatus::Stock->value,
            ]],
            'installations' => [['tire_id' => $nuevo->id, 'position_id' => $steer->id]],
        ], $this->admin);

        $this->assertEquals(TireStatus::Stock, $old->fresh()->status);
        $this->assertEquals(TireStatus::Instalada, $nuevo->fresh()->status);
        $this->assertEquals($steer->id, $nuevo->fresh()->currentLocation->position_id);
        $this->assertEquals(IncidentType::Cambio, $old->fresh()->incidents()->first()->type);
        $this->assertEquals(1000, $old->fresh()->accumulated_km);
    }

    public function test_sibling_tire_does_not_inherit_change_odometer(): void
    {
        $unit = $this->createTractor();
        [$left] = $this->purchaseTires(1, 50304, 'FH:01');
        [$right] = $this->purchaseTires(1, 50305, 'FH:01');
        [$replacement] = $this->purchaseTires(1, 50306, 'FH:01');
        $steer = $unit->configuration->positions()
            ->where('axle_number', 1)
            ->where('is_spare', false)
            ->orderBy('sort_order')
            ->get();
        $ops = app(TireOperationService::class);
        $ops->execute($unit, [
            'odometer' => 180000,
            'installations' => [
                ['tire_id' => $left->id, 'position_id' => $steer[0]->id],
                ['tire_id' => $right->id, 'position_id' => $steer[1]->id],
            ],
        ], $this->admin);

        $this->get(route('units.show', $unit))->assertOk();
        $this->post(route('units.slot', $unit), [
            '_token' => csrf_token(),
            'action' => 'cambio',
            'odometer' => 200000,
            'position_id' => $steer[0]->id,
            'expected_tire_id' => $left->id,
            'tire_id' => $replacement->id,
        ])->assertRedirect();

        $this->assertSame(20000, (int) $left->fresh()->accumulated_km);
        $this->assertSame(0, (int) $right->fresh()->accumulated_km);
        $rightSegment = $right->fresh()->openAssignment->openSegment;
        $this->assertSame(180000, (int) $rightSegment->start_odometer);
        $this->assertNull($rightSegment->end_odometer);

        $ops->execute($unit, [
            'odometer' => 210000,
            'removals' => [['tire_id' => $right->id]],
        ], $this->admin);

        $this->assertSame(30000, (int) $right->fresh()->accumulated_km);
    }

    public function test_cambio_rejects_drive_tire_on_steer_position(): void
    {
        $unit = $this->createTractor();
        [$old] = $this->purchaseTires(1, 50302, 'FH:01');
        [$drive] = $this->purchaseTires(1, 50303, 'TR:01');
        $steer = $unit->configuration->positions()->where('axle_number', 1)->where('is_spare', false)->first();
        app(TireOperationService::class)->execute($unit, [
            'odometer' => 100000,
            'installations' => [['tire_id' => $old->id, 'position_id' => $steer->id]],
        ], $this->admin);

        $this->get(route('units.show', $unit))->assertOk();
        $this->post(route('units.slot', $unit), [
            '_token' => csrf_token(),
            'action' => 'cambio',
            'odometer' => 101000,
            'position_id' => $steer->id,
            'expected_tire_id' => $old->id,
            'tire_id' => $drive->id,
        ])->assertSessionHasErrors('operation');

        $this->assertEquals(TireStatus::Instalada, $old->fresh()->status);
        $this->assertEquals(TireStatus::Stock, $drive->fresh()->status);
    }

    public function test_map_pinchadura_sends_tire_to_repair(): void
    {
        $unit = $this->createTractor();
        [$tire] = $this->purchaseTires(1, 50310, 'FH:01');
        $steer = $unit->configuration->positions()->where('axle_number', 1)->where('is_spare', false)->first();
        $ops = app(TireOperationService::class);
        $ops->execute($unit, [
            'odometer' => 100000,
            'installations' => [['tire_id' => $tire->id, 'position_id' => $steer->id]],
        ], $this->admin);

        app(IncidentService::class)->register($tire, [
            'type' => IncidentType::Pinchadura->value,
            'odometer' => 102000,
            'unit_id' => $unit->id,
            'position_id' => $steer->id,
        ], $this->admin);
        $ops->execute($unit, [
            'odometer' => 102000,
            'notes' => 'Pinchadura',
            'removals' => [[
                'tire_id' => $tire->id,
                'reason_id' => MovementReason::where('code', 'PINCHADURA')->value('id'),
                'destination' => TireStatus::EnReparacion->value,
            ]],
        ], $this->admin);

        $this->assertEquals(TireStatus::EnReparacion, $tire->fresh()->status);
        $this->assertEquals(IncidentType::Pinchadura, $tire->fresh()->incidents()->first()->type);
        $this->assertEquals('REPARADA', $tire->fresh()->condition->value);
        $this->assertNull($tire->fresh()->currentLocation->unit_id);
    }

    public function test_sheet_uses_ubicacion_not_cajon(): void
    {
        $unit = $this->createTractor();
        $html = $this->get(route('units.show', $unit))->assertOk()->getContent();

        $this->assertStringNotContainsString('cajón', mb_strtolower($html));
        $this->assertStringNotContainsString('cajon', mb_strtolower($html));
        $this->assertStringContainsString('Ubicación', $html);
    }

    public function test_slot_map_includes_mounted_tire_detail(): void
    {
        $unit = $this->createTractor();
        [$tire] = $this->purchaseTires(1, 50320, 'FH:01');
        $steer = $unit->configuration->positions()->where('axle_number', 1)->where('is_spare', false)->first();
        app(TireOperationService::class)->execute($unit, [
            'odometer' => 100000,
            'installations' => [['tire_id' => $tire->id, 'position_id' => $steer->id]],
        ], $this->admin);

        $html = $this->get(route('units.show', $unit))->assertOk()->getContent();
        preg_match('/id="slotMap">(.+?)<\/script>/s', $html, $match);
        $slots = json_decode($match[1], true);
        $steerSlot = collect($slots)->firstWhere('id', $steer->id);

        $this->assertSame('FH:01 Nº50320', $steerSlot['tire']['name']);
        $this->assertSame('FH:01', $steerSlot['tire']['code']);
        $this->assertSame('Dirección', $steerSlot['tire']['application']);
        $this->assertNotEmpty($steerSlot['tire']['brand']);
        $this->assertNotEmpty($steerSlot['tire']['size']);
        $this->assertSame('Nueva', $steerSlot['tire']['condition']);
        $this->assertSame(1, $steerSlot['tire']['life']);
        $this->assertNotEmpty($steerSlot['tire']['zones']);
        $this->assertStringContainsString('Retirar', $html);
        $this->assertStringContainsString('Medición', $html);
        $this->assertStringContainsString('Incidencia', $html);
    }

    public function test_map_retirar_frees_location(): void
    {
        $unit = $this->createTractor();
        [$tire] = $this->purchaseTires(1, 50330, 'FH:01');
        $steer = $unit->configuration->positions()->where('axle_number', 1)->where('is_spare', false)->first();
        app(TireOperationService::class)->execute($unit, [
            'odometer' => 100000,
            'installations' => [['tire_id' => $tire->id, 'position_id' => $steer->id]],
        ], $this->admin);

        $this->get(route('units.show', $unit))->assertOk();
        $this->post(route('units.slot', $unit), [
            '_token' => csrf_token(),
            'action' => 'retirar',
            'odometer' => 101000,
            'position_id' => $steer->id,
            'expected_tire_id' => $tire->id,
            'reason_id' => MovementReason::where('code', 'INSPECCION')->value('id'),
            'destination' => TireStatus::Reserva->value,
        ])->assertRedirect();

        $this->assertEquals(TireStatus::Reserva, $tire->fresh()->status);
        $this->assertNull($tire->fresh()->currentLocation->unit_id);
    }

    public function test_map_incidencia_keeps_tire_mounted(): void
    {
        $unit = $this->createTractor();
        [$tire] = $this->purchaseTires(1, 50340, 'FH:01');
        $steer = $unit->configuration->positions()->where('axle_number', 1)->where('is_spare', false)->first();
        app(TireOperationService::class)->execute($unit, [
            'odometer' => 100000,
            'installations' => [['tire_id' => $tire->id, 'position_id' => $steer->id]],
        ], $this->admin);

        $this->get(route('units.show', $unit))->assertOk();
        $this->post(route('units.slot', $unit), [
            '_token' => csrf_token(),
            'action' => 'incidencia',
            'odometer' => 101000,
            'position_id' => $steer->id,
            'expected_tire_id' => $tire->id,
            'incident_type' => IncidentType::Sopladura->value,
            'description' => 'Sopladura en flanco',
        ])->assertRedirect();

        $this->assertEquals(TireStatus::Instalada, $tire->fresh()->status);
        $this->assertEquals($unit->id, $tire->fresh()->currentLocation->unit_id);
        $this->assertEquals(IncidentType::Sopladura, $tire->fresh()->incidents()->first()->type);
    }

    public function test_map_medicion_saves_tread(): void
    {
        $unit = $this->createTractor();
        [$tire] = $this->purchaseTires(1, 50350, 'FH:01');
        $steer = $unit->configuration->positions()->where('axle_number', 1)->where('is_spare', false)->first();
        app(TireOperationService::class)->execute($unit, [
            'odometer' => 100000,
            'installations' => [['tire_id' => $tire->id, 'position_id' => $steer->id]],
        ], $this->admin);

        $tire->load('size.zones');
        $readings = $tire->size->zones->values()->map(fn ($zone, $i) => [
            'zone_id' => $zone->id,
            'millimeters' => 12.5,
        ])->all();

        $this->get(route('units.show', $unit))->assertOk();
        $this->post(route('units.slot', $unit), [
            '_token' => csrf_token(),
            'action' => 'medicion',
            'odometer' => 101000,
            'position_id' => $steer->id,
            'expected_tire_id' => $tire->id,
            'readings' => $readings,
        ])->assertRedirect();

        $this->assertEquals(12.5, (float) $tire->fresh()->current_tread_min);
        $this->assertEquals($unit->id, $tire->fresh()->currentLocation->unit_id);
    }

    public function test_swap_exchanges_two_mounted_steer_tires(): void
    {
        $unit = $this->createTractor();
        [$left, $right] = $this->purchaseTires(2, 50400, 'FH:01');
        $steer = $unit->configuration->positions()
            ->where('axle_number', 1)
            ->where('is_spare', false)
            ->orderBy('sort_order')
            ->get();
        $ops = app(TireOperationService::class);
        $ops->execute($unit, [
            'odometer' => 100000,
            'installations' => [
                ['tire_id' => $left->id, 'position_id' => $steer[0]->id],
                ['tire_id' => $right->id, 'position_id' => $steer[1]->id],
            ],
        ], $this->admin);

        $ops->rotate($unit, $left->id, $steer[1]->id, 101000, $this->admin);

        $this->assertEquals($steer[1]->id, $left->fresh()->currentLocation->position_id);
        $this->assertEquals($steer[0]->id, $right->fresh()->currentLocation->position_id);
        $this->assertEquals(TireStatus::Instalada, $left->fresh()->status);
        $this->assertEquals(1, $left->assignments()->whereNull('ended_at')->count());
    }

    public function test_longitudinal_pattern_swaps_steer_and_tandem(): void
    {
        $unit = $this->createTractor();
        [$steerLeft, $steerRight] = $this->purchaseTires(2, 50420, 'FH:01');
        $drive = $this->purchaseTires(8, 50430, 'TR:01');
        $steer = $unit->configuration->positions()->where('axle_number', 1)->where('is_spare', false)->orderBy('sort_order')->get();
        $rear = $unit->configuration->positions()
            ->whereIn('axle_number', [2, 3])
            ->where('is_spare', false)
            ->orderBy('sort_order')
            ->get();
        $ops = app(TireOperationService::class);
        $installations = [
            ['tire_id' => $steerLeft->id, 'position_id' => $steer[0]->id],
            ['tire_id' => $steerRight->id, 'position_id' => $steer[1]->id],
        ];
        foreach ($rear as $i => $position) {
            $installations[] = ['tire_id' => $drive[$i]->id, 'position_id' => $position->id];
        }
        $ops->execute($unit, ['odometer' => 100000, 'installations' => $installations], $this->admin);

        $e2Ext = $unit->configuration->positions()->where('axle_number', 2)->where('side', 'IZQ')->where('dual', 'EXT')->first();
        $e3Ext = $unit->configuration->positions()->where('axle_number', 3)->where('side', 'IZQ')->where('dual', 'EXT')->first();
        $fromE2 = TireCurrentLocation::where('unit_id', $unit->id)->where('position_id', $e2Ext->id)->value('tire_id');

        $layout = $unit->fresh()->tireLayout();
        $fit = app(PositionFitService::class);
        $pattern = collect(app(RotationPatternService::class)->forLayout($layout, $fit))->firstWhere('code', 'longitudinal');
        $this->assertNotNull($pattern);
        $this->assertTrue($pattern['ready']);

        $ops->applyPattern($unit, $pattern['pairs'], 101000, $this->admin);

        $this->assertEquals($steer[1]->id, $steerLeft->fresh()->currentLocation->position_id);
        $this->assertEquals($steer[0]->id, $steerRight->fresh()->currentLocation->position_id);
        $this->assertEquals($fromE2, TireCurrentLocation::where('unit_id', $unit->id)->where('position_id', $e3Ext->id)->value('tire_id'));
    }

    public function test_sheet_shows_kananfleet_controls(): void
    {
        $unit = $this->createTractor();
        $this->purchaseTires(1, 50450, 'FH:01');
        $html = $this->get(route('units.show', $unit))->assertOk()->getContent();

        $this->assertStringNotContainsString('Disponibles', $html);
        $this->assertStringContainsString('Auxilio', $html);
        $this->assertStringContainsString('Longitudinal', $html);
        $this->assertStringContainsString('En X', $html);
        $this->assertStringContainsString('Diagonal', $html);
        $this->assertStringContainsString('Tocá el auxilio del mapa para instalar', $html);
        $this->assertStringContainsString('Cambio', $html);
        $this->assertStringContainsString('Sale', $html);
        $this->assertStringContainsString('Km de la unidad en esta operación', $html);
        $this->assertStringNotContainsString('id="sheetOdometer"', $html);
    }

    public function test_lineal_tank_accepts_matching_width_and_rejects_the_other(): void
    {
        $tractor = $this->createTractor();
        $tank = $this->createLinealTrailer(385);
        [$wrong] = $this->purchaseTires(1, 50500, 'FR:01', '295/80 R22.5');
        [$ok] = $this->purchaseTires(1, 50510, 'FR:01', '385/65 R22.5');
        $slot = $tank->configuration->positions()->where('is_spare', false)->first();
        $ops = app(TireOperationService::class);
        app(CouplingService::class)->couple($tractor, $tank, 100000, $this->admin);

        try {
            $ops->execute($tank, [
                'odometer' => 100000,
                'installations' => [['tire_id' => $wrong->id, 'position_id' => $slot->id]],
            ], $this->admin);
            $this->fail('Tenía que rechazar la 295 en un tanque 385.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('lineales de 385', $e->getMessage());
        }

        $ops->execute($tank, [
            'odometer' => 100000,
            'installations' => [['tire_id' => $ok->id, 'position_id' => $slot->id]],
        ], $this->admin);

        $this->assertEquals($slot->id, $ok->fresh()->currentLocation->position_id);
        $this->assertSame('Eje 1 — Lineal izquierdo', $slot->name);
    }

    public function test_lineal_tank_stock_hides_wrong_width_and_create_requires_measure(): void
    {
        $tank = $this->createLinealTrailer(385);
        $this->purchaseTires(1, 50600, 'FR:01', '295/80 R22.5');
        $this->purchaseTires(1, 50610, 'FR:01', '385/65 R22.5');

        $slot = $tank->configuration->positions()->where('is_spare', false)->first();
        $html = $this->get(route('units.show', $tank))->assertOk()->getContent();
        $this->assertStringContainsString('Lineal 385', $html);
        $this->assertStringNotContainsString('FR:01 Nº50600', $html);
        $this->assertStringNotContainsString('FR:01 Nº50610', $html);

        $ids = collect($this->getJson(route('units.stock', ['unit' => $tank, 'position_id' => $slot->id]))->json('data'))->pluck('id');
        $this->assertTrue($ids->contains(fn ($id) => \App\Models\Tire::find($id)?->individual_number == 50610));
        $this->assertFalse($ids->contains(fn ($id) => \App\Models\Tire::find($id)?->individual_number == 50600));

        $this->get(route('units.create'))->assertOk();
        $this->post(route('units.store'), [
            '_token' => csrf_token(),
            'fleet_id' => $tank->fleet_id,
            'base_id' => $tank->base_id,
            'unit_type_id' => $tank->unit_type_id,
            'unit_configuration_id' => $tank->unit_configuration_id,
            'plate' => 'TNK999',
        ])->assertSessionHasErrors('specs.tire_width');

        $this->post(route('units.store'), [
            '_token' => csrf_token(),
            'fleet_id' => $tank->fleet_id,
            'base_id' => $tank->base_id,
            'unit_type_id' => $tank->unit_type_id,
            'unit_configuration_id' => $tank->unit_configuration_id,
            'plate' => 'TNK998',
            'specs' => ['tire_width' => 295],
        ])->assertRedirect();

        $this->assertSame(295, FleetUnit::where('plate', 'TNK998')->first()->allowedTireWidth());
    }

    public function test_lineal_295_tank_rejects_gomon(): void
    {
        $tractor = $this->createTractor();
        $tank = $this->createLinealTrailer(295);
        [$gomon] = $this->purchaseTires(1, 50520, 'FR:01', '385/65 R22.5');
        $slot = $tank->configuration->positions()->where('is_spare', false)->first();
        app(CouplingService::class)->couple($tractor, $tank, 100000, $this->admin);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('lineales de 295');

        app(TireOperationService::class)->execute($tank, [
            'odometer' => 100000,
            'installations' => [['tire_id' => $gomon->id, 'position_id' => $slot->id]],
        ], $this->admin);
    }

    public function test_each_design_belongs_to_one_brand_with_factory_sizes(): void
    {
        $fh = TireModel::with('brand', 'sizes')->where('code', 'FH:01')->get();
        $this->assertCount(1, $fh);
        $this->assertSame('Pirelli', $fh->first()->brand->name);
        $this->assertTrue($fh->first()->sizes->contains('code', '295/80 R22.5'));
        $this->assertTrue($fh->first()->sizes->contains('code', '385/65 R22.5'));

        $tr = TireModel::with('sizes')->where('code', 'TR:01')->first();
        $this->assertSame('Pirelli', $tr->brand->name);
        $this->assertFalse($tr->sizes->contains('code', '385/65 R22.5'));

        $this->assertTrue(TireModel::where('code', 'ST:01')->whereHas('brand', fn ($q) => $q->where('name', 'Pirelli'))->exists());
        $this->assertTrue(TireModel::where('code', 'SR-200')->whereHas('brand', fn ($q) => $q->where('name', 'Fate'))->exists());
        $this->assertTrue(TireModel::where('code', 'TR-500')->whereHas('brand', fn ($q) => $q->where('name', 'Fate'))->exists());
        $this->assertTrue(TireModel::where('code', 'X Multi Z')->whereHas('brand', fn ($q) => $q->where('name', 'Michelin'))->exists());
        $this->assertFalse(TireModel::where('code', 'FH:01')->whereHas('brand', fn ($q) => $q->where('name', 'Michelin'))->exists());
    }

    public function test_cannot_purchase_pirelli_design_under_another_brand(): void
    {
        $fh = TireModel::with('sizes')->where('code', 'FH:01')->first();
        $michelin = TireBrand::where('name', 'Michelin')->first();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('es de Pirelli');

        app(PurchaseService::class)->create([
            'supplier_id' => Supplier::first()->id,
            'base_id' => Base::first()->id,
            'purchased_at' => now()->toDateString(),
            'items' => [[
                'tire_brand_id' => $michelin->id,
                'tire_model_id' => $fh->id,
                'tire_size_id' => $fh->sizes->first()->id,
                'quantity' => 1,
                'first_number' => 60000,
            ]],
        ], $this->admin);
    }

    public function test_cannot_purchase_drive_design_in_gomon_size(): void
    {
        $tr = TireModel::where('code', 'TR:01')->first();
        $gomon = TireSize::where('code', '385/65 R22.5')->first();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no se fabrica en esa medida');

        app(PurchaseService::class)->create([
            'supplier_id' => Supplier::first()->id,
            'base_id' => Base::first()->id,
            'purchased_at' => now()->toDateString(),
            'items' => [[
                'tire_brand_id' => $tr->tire_brand_id,
                'tire_model_id' => $tr->id,
                'tire_size_id' => $gomon->id,
                'quantity' => 1,
                'first_number' => 60010,
            ]],
        ], $this->admin);
    }

    public function test_purchase_form_lists_models_only_inside_their_brand(): void
    {
        $html = $this->get(route('purchases.create'))->assertOk()->getContent();
        preg_match('/id="tireCatalog">(.+?)<\/script>/s', $html, $match);
        $this->assertNotEmpty($match);
        $catalog = json_decode($match[1], true);
        $pirelli = collect($catalog['brands'])->firstWhere('name', 'Pirelli');
        $michelin = collect($catalog['brands'])->firstWhere('name', 'Michelin');

        $this->assertTrue(collect($pirelli['models'])->contains('code', 'FH:01'));
        $this->assertFalse(collect($michelin['models'])->contains('code', 'FH:01'));
        $this->assertTrue(collect($michelin['models'])->contains('code', 'XZE2+'));
    }

    public function test_audit_page_uses_natural_language_for_movements(): void
    {
        $tractor = $this->createTractor();
        $trailer = $this->createTrailer();
        app(CouplingService::class)->couple($tractor, $trailer, 100000, $this->admin);

        $this->get(route('reports.audit'))
            ->assertOk()
            ->assertSee('Movimientos')
            ->assertSee('Acopló unidades')
            ->assertSee($tractor->plate)
            ->assertSee($trailer->plate)
            ->assertSee('100.000 km')
            ->assertDontSee('coupling.created')
            ->assertDontSee('UnitCoupling #');
    }
}
