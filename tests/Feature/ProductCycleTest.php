<?php

namespace Tests\Feature;

use App\Enums\TireCondition;
use App\Enums\TireStatus;
use App\Enums\UserRole;
use App\Models\Base;
use App\Models\Fleet;
use App\Models\FleetUnit;
use App\Models\MovementReason;
use App\Models\UnitConfiguration;
use App\Models\UnitType;
use App\Models\User;
use App\Services\TireOperationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class ProductCycleTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    public function test_puncture_returns_to_stock_without_new_life_and_can_reinstall(): void
    {
        $unit = $this->createTractor();
        [$tire] = $this->purchaseTires(1, 71001, 'FH:01');
        $steer = $unit->configuration->positions()->where('axle_number', 1)->where('is_spare', false)->first();
        app(TireOperationService::class)->execute($unit, [
            'odometer' => 100000,
            'installations' => [['tire_id' => $tire->id, 'position_id' => $steer->id]],
        ], $this->admin);

        $this->get(route('units.show', $unit))->assertOk();
        $this->post(route('units.slot', $unit), [
            '_token' => csrf_token(),
            'action' => 'pinchadura',
            'odometer' => 102000,
            'position_id' => $steer->id,
            'expected_tire_id' => $tire->id,
            'notes' => 'Pinchó en ruta',
        ])->assertRedirect();

        $tire = $tire->fresh();
        $this->assertEquals(TireStatus::EnReparacion, $tire->status);
        $this->assertEquals(TireCondition::Reparada, $tire->condition);
        $life = (int) ($tire->currentLifecycle?->life_number ?? 1);

        $this->post(route('tires.return-stock', $tire), [
            '_token' => csrf_token(),
            'notes' => 'Parche interno',
        ])->assertRedirect();

        $tire = $tire->fresh();
        $this->assertEquals(TireStatus::Stock, $tire->status);
        $this->assertEquals(TireCondition::Reparada, $tire->condition);
        $this->assertEquals($life, (int) ($tire->currentLifecycle?->life_number ?? 1));
        $this->assertEquals(1, $tire->lifecycles()->count());

        app(TireOperationService::class)->execute($unit, [
            'odometer' => 102500,
            'installations' => [['tire_id' => $tire->id, 'position_id' => $steer->id]],
        ], $this->admin);

        $this->assertEquals(TireStatus::Instalada, $tire->fresh()->status);
        $this->assertEquals(TireCondition::Reparada, $tire->fresh()->condition);
        $this->assertEquals($life, (int) $tire->fresh()->currentLifecycle?->life_number);
    }

    public function test_slot_install_opens_lifecycle_when_missing(): void
    {
        $unit = $this->createTractor();
        $model = \App\Models\TireModel::where('code', 'FH:01')->firstOrFail();
        $tire = \App\Models\Tire::factory()->create([
            'tire_brand_id' => $model->tire_brand_id,
            'tire_model_id' => $model->id,
            'tire_size_id' => $model->sizes()->firstOrFail()->id,
            'current_lifecycle_id' => null,
        ]);
        app(\App\Services\LocationService::class)->place(
            $tire,
            \App\Enums\LocationKind::Stock,
            \App\Models\Base::query()->firstOrFail()->id,
        );
        $steer = $unit->configuration->positions()->where('axle_number', 1)->where('is_spare', false)->first();

        $this->get(route('units.show', $unit))->assertOk();
        $this->post(route('units.slot', $unit), [
            '_token' => csrf_token(),
            'action' => 'install',
            'odometer' => 100000,
            'position_id' => $steer->id,
            'tire_id' => $tire->id,
        ])->assertRedirect(route('units.show', $unit));

        $tire = $tire->fresh();
        $this->assertNotNull($tire->current_lifecycle_id);
        $this->assertEquals(\App\Enums\TireStatus::Instalada, $tire->status);
    }

    public function test_reserve_returns_to_stock(): void
    {
        $unit = $this->createTractor();
        [$tire] = $this->purchaseTires(1, 71010, 'FH:01');
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

        $this->post(route('tires.return-stock', $tire->fresh()), ['_token' => csrf_token()])->assertRedirect();
        $this->assertEquals(TireStatus::Stock, $tire->fresh()->status);
    }

    public function test_consulta_does_not_see_another_fleet_unit(): void
    {
        $visible = $this->createTractor();
        $otherFleet = Fleet::create(['name' => 'Flota ajena', 'code' => 'AJENA', 'is_active' => true]);
        $otherBase = Base::create(['name' => 'Base ajena', 'code' => 'BAJ', 'is_active' => true]);
        $hidden = FleetUnit::create([
            'fleet_id' => $otherFleet->id,
            'base_id' => $otherBase->id,
            'unit_type_id' => UnitType::where('code', 'TRACTOR')->first()->id,
            'unit_configuration_id' => UnitConfiguration::where('code', '6X4')->first()->id,
            'plate' => 'AJN123',
            'current_odometer' => 10000,
            'status' => 'ACTIVA',
        ]);

        $consulta = User::factory()->create(['role' => UserRole::Consulta]);
        $consulta->fleets()->sync([$visible->fleet_id]);

        $this->actingAs($consulta)
            ->get(route('units.show', $hidden))
            ->assertNotFound();
        $this->actingAs($consulta)
            ->get(route('units.show', $visible))
            ->assertOk();
    }

    public function test_consulta_cannot_return_stock_via_api(): void
    {
        [$tire] = $this->purchaseTires(1, 71020);
        $consulta = User::factory()->create(['role' => UserRole::Consulta]);
        $consulta->fleets()->sync(Fleet::pluck('id'));
        $consulta->bases()->sync(Base::pluck('id'));

        $this->actingAs($consulta)
            ->postJson('/api/v1/tires/'.$tire->id.'/return-stock')
            ->assertForbidden();
    }

    public function test_operario_ficha_hides_recap_and_shows_timeline(): void
    {
        $unit = $this->createTractor();
        [$tire] = $this->purchaseTires(1, 71030, 'FH:01');
        $steer = $unit->configuration->positions()->where('axle_number', 1)->where('is_spare', false)->first();
        app(TireOperationService::class)->execute($unit, [
            'odometer' => 100000,
            'installations' => [['tire_id' => $tire->id, 'position_id' => $steer->id]],
        ], $this->admin);

        $operario = User::factory()->create(['role' => UserRole::Operario]);
        $operario->fleets()->sync(Fleet::pluck('id'));
        $operario->bases()->sync(Base::pluck('id'));

        $html = $this->actingAs($operario)->get(route('tires.show', $tire))->assertOk()->getContent();
        $this->assertStringNotContainsString('value="RECAPADO"', $html);
        $this->assertStringContainsString('Historial', $html);
        $this->assertStringContainsString('Se montó en la unidad', $html);
        $this->assertStringContainsString('Alta / montaje', $html);
        $this->assertStringNotContainsString('Dar de baja', $html);
        $timelineHtml = substr($html, (int) strpos($html, 'class="timeline"'));
        $this->assertLessThan(
            strpos($timelineHtml, 'Se montó en la unidad'),
            strpos($timelineHtml, 'Alta por compra'),
            'El historial debe listar el alta antes del montaje (más viejo arriba).'
        );

        $adminHtml = $this->actingAs($this->admin)->get(route('tires.show', $tire))->assertOk()->getContent();
        $this->assertStringContainsString('value="RECAPADO"', $adminHtml);
        $this->assertStringContainsString('Dar de baja', $adminHtml);
    }

    public function test_search_finds_plate_and_tire_number(): void
    {
        $unit = $this->createTractor();
        [$tire] = $this->purchaseTires(1, 71040);

        $this->get(route('search', ['q' => $unit->plate]))->assertRedirect(route('units.show', $unit));
        $this->get(route('search', ['q' => (string) $tire->individual_number]))->assertRedirect(route('tires.show', $tire));

        $this->getJson(route('search.suggest', ['q' => mb_substr($unit->plate, 0, 3)]))
            ->assertOk()
            ->assertJsonFragment(['label' => $unit->plate, 'type' => 'unit']);
        $this->getJson(route('search.suggest', ['q' => (string) $tire->individual_number]))
            ->assertOk()
            ->assertJsonFragment(['type' => 'tire']);
    }

    public function test_inactive_user_is_logged_out(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Operario,
            'is_active' => true,
        ]);
        $this->actingAs($user);
        $user->update(['is_active' => false]);

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_error_pages_are_branded(): void
    {
        $this->get('/esta-ruta-no-existe-rodante')
            ->assertNotFound()
            ->assertSee('Rodante')
            ->assertSee('err-body', false)
            ->assertSee('err-mark', false);
    }

    public function test_dashboard_shows_repair_queue(): void
    {
        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('Cola de reparación', $html);
        $this->assertStringContainsString('Profundidad crítica', $html);
        $this->assertStringContainsString('80,000 km', $html);
    }
}
