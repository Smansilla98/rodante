<?php

namespace Tests\Feature;

use App\Enums\TireStatus;
use App\Exceptions\SheetConflictException;
use App\Models\MovementReason;
use App\Services\TireOperationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class SheetConcurrencyTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    public function test_stale_removal_after_rotation_does_not_take_the_tire_off(): void
    {
        $unit = $this->createTractor();
        [$tire] = $this->purchaseTires(1, 60001, 'FH:01');
        $steer = $unit->configuration->positions()
            ->where('axle_number', 1)
            ->where('is_spare', false)
            ->orderBy('sort_order')
            ->get();
        $from = $steer[0];
        $to = $steer[1];
        $ops = app(TireOperationService::class);
        $ops->execute($unit, [
            'odometer' => 100000,
            'installations' => [['tire_id' => $tire->id, 'position_id' => $from->id]],
        ], $this->admin);
        $ops->rotate($unit, $tire->id, $to->id, 101000, $this->admin);

        try {
            $ops->execute($unit, [
                'odometer' => 102000,
                'removals' => [[
                    'tire_id' => $tire->id,
                    'position_id' => $from->id,
                    'destination' => TireStatus::Stock->value,
                ]],
            ], $this->admin);
            $this->fail('Se esperaba conflicto de ocupación.');
        } catch (SheetConflictException $e) {
            $this->assertSame('Otro operario ya cambió esta ubicación. Recargá la planilla.', $e->getMessage());
        }

        $this->assertEquals(TireStatus::Instalada, $tire->fresh()->status);
        $this->assertEquals($to->id, $tire->fresh()->currentLocation->position_id);
    }

    public function test_second_install_on_taken_slot_is_a_conflict(): void
    {
        $unit = $this->createTractor();
        [$a, $b] = $this->purchaseTires(2, 60010, 'FH:01');
        $position = $unit->configuration->positions()->where('axle_number', 1)->where('is_spare', false)->first();
        $ops = app(TireOperationService::class);
        $ops->execute($unit, [
            'odometer' => 100000,
            'installations' => [['tire_id' => $a->id, 'position_id' => $position->id, 'expect_empty' => true]],
        ], $this->admin);

        $this->expectException(SheetConflictException::class);
        $ops->execute($unit, [
            'odometer' => 100000,
            'installations' => [['tire_id' => $b->id, 'position_id' => $position->id, 'expect_empty' => true]],
        ], $this->admin);
    }

    public function test_slot_form_shows_conflict_when_expected_tire_no_longer_there(): void
    {
        $unit = $this->createTractor();
        [$first, $second] = $this->purchaseTires(2, 60020, 'FH:01');
        $steer = $unit->configuration->positions()
            ->where('axle_number', 1)
            ->where('is_spare', false)
            ->orderBy('sort_order')
            ->get();
        $ops = app(TireOperationService::class);
        $ops->execute($unit, [
            'odometer' => 100000,
            'installations' => [['tire_id' => $first->id, 'position_id' => $steer[0]->id]],
        ], $this->admin);
        $ops->rotate($unit, $first->id, $steer[1]->id, 101000, $this->admin);
        $ops->execute($unit, [
            'odometer' => 101000,
            'installations' => [['tire_id' => $second->id, 'position_id' => $steer[0]->id]],
        ], $this->admin);

        $this->get(route('units.show', $unit))->assertOk();
        $this->from(route('units.show', $unit))
            ->post(route('units.slot', $unit), [
                '_token' => csrf_token(),
                'action' => 'retirar',
                'odometer' => 102000,
                'position_id' => $steer[0]->id,
                'expected_tire_id' => $first->id,
                'reason_id' => MovementReason::where('code', 'INSPECCION')->value('id'),
                'destination' => TireStatus::Stock->value,
            ])
            ->assertRedirect(route('units.show', $unit))
            ->assertSessionHasErrors([
                'operation' => 'Otro operario ya cambió esta ubicación. Recargá la planilla.',
            ]);

        $this->assertEquals(TireStatus::Instalada, $second->fresh()->status);
        $this->assertEquals($steer[0]->id, $second->fresh()->currentLocation->position_id);
        $this->assertEquals(TireStatus::Instalada, $first->fresh()->status);
    }
}
