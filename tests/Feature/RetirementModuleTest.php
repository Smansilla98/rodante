<?php

namespace Tests\Feature;

use App\Enums\TireStatus;
use App\Enums\UserRole;
use App\Models\MovementReason;
use App\Models\User;
use App\Services\TireOperationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class RetirementModuleTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    public function test_guest_is_redirected_from_bajas(): void
    {
        auth()->logout();
        $this->app['auth']->forgetGuards();

        $this->get(route('retirements.index'))->assertRedirect(route('login'));
    }

    public function test_operario_cannot_open_bajas_module(): void
    {
        $operario = User::factory()->create([
            'role' => UserRole::Operario,
            'company_id' => $this->admin->company_id,
        ]);

        $this->actingAs($operario)
            ->get(route('retirements.index'))
            ->assertForbidden();
    }

    public function test_index_lists_stock_as_eligible_and_installed_as_blocked(): void
    {
        [$stockTire, $mountedTire] = $this->purchaseTires(2, 77001);
        $unit = $this->createTractor();
        $position = $unit->configuration->positions()->where('is_spare', false)->firstOrFail();

        app(TireOperationService::class)->execute($unit, [
            'odometer' => 120000,
            'installations' => [['tire_id' => $mountedTire->id, 'position_id' => $position->id]],
        ], $this->admin);

        $this->get(route('retirements.index'))
            ->assertOk()
            ->assertSee('Listas para dar de baja')
            ->assertSee('Cómo dar de baja')
            ->assertSee('data-retire-count', false)
            ->assertSee((string) $stockTire->individual_number)
            ->assertSee('No se pueden dar de baja')
            ->assertSee((string) $mountedTire->individual_number)
            ->assertSee('Abrir planilla y retirar')
            ->assertDontSee('action-card--danger', false);
    }

    public function test_store_retires_eligible_tire(): void
    {
        [$tire] = $this->purchaseTires(1, 77010);
        $reason = MovementReason::where('applies_to', 'BAJA')->firstOrFail();

        $this->withSession(['_token' => 'test-csrf-token'])
            ->post(route('retirements.store', $tire), [
                '_token' => 'test-csrf-token',
                'reason_id' => $reason->id,
                'notes' => 'Fin de vida útil',
            ])->assertRedirect(route('retirements.index'));

        $this->assertSame(TireStatus::DeBaja, $tire->fresh()->status);
        $this->assertNotNull($tire->fresh()->retired_at);
    }

    public function test_store_rejects_tire_still_on_unit(): void
    {
        [$tire] = $this->purchaseTires(1, 77020);
        $unit = $this->createTractor();
        $position = $unit->configuration->positions()->where('is_spare', false)->firstOrFail();
        $reason = MovementReason::where('applies_to', 'BAJA')->firstOrFail();

        app(TireOperationService::class)->execute($unit, [
            'odometer' => 130000,
            'installations' => [['tire_id' => $tire->id, 'position_id' => $position->id]],
        ], $this->admin);

        $this->withSession(['_token' => 'test-csrf-token'])
            ->from(route('retirements.index'))
            ->post(route('retirements.store', $tire), [
                '_token' => 'test-csrf-token',
                'reason_id' => $reason->id,
                'notes' => 'Intento inválido',
            ])
            ->assertRedirect(route('retirements.index', ['q' => $tire->individual_number]))
            ->assertSessionHasErrors('retire');

        $this->assertSame(TireStatus::Instalada, $tire->fresh()->status);
        $this->assertNull($tire->fresh()->retired_at);
    }

    public function test_bulk_retires_selected_tires(): void
    {
        [$a, $b, $c] = $this->purchaseTires(3, 77100);
        $reason = MovementReason::where('applies_to', 'BAJA')->firstOrFail();

        $this->withSession(['_token' => 'test-csrf-token'])
            ->post(route('retirements.bulk'), [
                '_token' => 'test-csrf-token',
                'tire_ids' => [$a->id, $b->id],
                'reason_id' => $reason->id,
                'notes' => 'Baja masiva de prueba',
            ])
            ->assertRedirect(route('retirements.index'))
            ->assertSessionHas('success');

        $this->assertSame(TireStatus::DeBaja, $a->fresh()->status);
        $this->assertSame(TireStatus::DeBaja, $b->fresh()->status);
        $this->assertSame(TireStatus::Stock, $c->fresh()->status);
    }

    public function test_bulk_skips_mounted_and_retires_the_rest(): void
    {
        [$stock, $mounted] = $this->purchaseTires(2, 77110);
        $unit = $this->createTractor();
        $position = $unit->configuration->positions()->where('is_spare', false)->firstOrFail();
        $reason = MovementReason::where('applies_to', 'BAJA')->firstOrFail();

        app(TireOperationService::class)->execute($unit, [
            'odometer' => 140000,
            'installations' => [['tire_id' => $mounted->id, 'position_id' => $position->id]],
        ], $this->admin);

        $this->withSession(['_token' => 'test-csrf-token'])
            ->post(route('retirements.bulk'), [
                '_token' => 'test-csrf-token',
                'tire_ids' => [$stock->id, $mounted->id],
                'reason_id' => $reason->id,
            ])
            ->assertRedirect(route('retirements.index'))
            ->assertSessionHas('success')
            ->assertSessionHasErrors('retire');

        $this->assertSame(TireStatus::DeBaja, $stock->fresh()->status);
        $this->assertSame(TireStatus::Instalada, $mounted->fresh()->status);
    }

    public function test_bulk_requires_selection(): void
    {
        $reason = MovementReason::where('applies_to', 'BAJA')->firstOrFail();

        $this->withSession(['_token' => 'test-csrf-token'])
            ->from(route('retirements.index'))
            ->post(route('retirements.bulk'), [
                '_token' => 'test-csrf-token',
                'reason_id' => $reason->id,
            ])
            ->assertRedirect(route('retirements.index'))
            ->assertSessionHasErrors('tire_ids');
    }

    public function test_nav_shows_dar_de_baja_for_admin(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dar de baja')
            ->assertSee(route('retirements.index'), false);
    }
}
