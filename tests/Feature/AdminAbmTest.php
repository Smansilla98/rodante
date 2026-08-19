<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Base;
use App\Models\OdometerReading;
use App\Models\Supplier;
use App\Models\TireBrand;
use App\Models\TireModel;
use App\Models\User;
use App\Services\PurchaseService;
use App\Services\TireOperationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class AdminAbmTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    public function test_admin_can_update_and_delete_unused_brand(): void
    {
        $this->get(route('brands.index'))->assertOk();
        $brand = TireBrand::create(['name' => 'Marca Test ABM', 'is_active' => true]);

        $this->put(route('brands.update', $brand), [
            '_token' => csrf_token(),
            'name' => 'Marca Editada',
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('tire_brands', ['id' => $brand->id, 'name' => 'Marca Editada']);

        $this->delete(route('brands.destroy', $brand), ['_token' => csrf_token()])->assertRedirect();
        $this->assertDatabaseMissing('tire_brands', ['id' => $brand->id]);
    }

    public function test_admin_cannot_delete_brand_with_models(): void
    {
        $this->get(route('brands.index'))->assertOk();
        $brand = TireBrand::has('models')->first();

        $this->from(route('brands.index'))
            ->delete(route('brands.destroy', $brand), ['_token' => csrf_token()])
            ->assertRedirect(route('brands.index'));

        $this->assertDatabaseHas('tire_brands', ['id' => $brand->id]);
    }

    public function test_consulta_cannot_open_catalog_abm(): void
    {
        $consulta = User::factory()->create(['role' => UserRole::Consulta]);

        $this->actingAs($consulta)
            ->get(route('brands.index'))
            ->assertForbidden();
    }

    public function test_admin_can_edit_unit_master_data(): void
    {
        $unit = $this->createTractor();
        $this->get(route('units.edit', $unit))->assertOk()->assertSee('Editar');

        $this->put(route('units.update', $unit), [
            '_token' => csrf_token(),
            'fleet_id' => $unit->fleet_id,
            'base_id' => $unit->base_id,
            'plate' => 'ABC123',
            'status' => 'ACTIVA',
        ])->assertRedirect(route('units.show', $unit));

        $this->assertSame('ABC123', $unit->fresh()->plate);
    }

    public function test_admin_can_discard_draft_purchase(): void
    {
        $this->get(route('purchases.create'))->assertOk();
        $purchase = app(PurchaseService::class)->create([
            'supplier_id' => Supplier::first()->id,
            'base_id' => Base::first()->id,
            'purchased_at' => now()->toDateString(),
            'items' => [[
                'tire_brand_id' => TireModel::first()->tire_brand_id,
                'tire_model_id' => TireModel::first()->id,
                'tire_size_id' => TireModel::first()->sizes()->first()->id,
                'quantity' => 1,
                'first_number' => 90001,
            ]],
        ], $this->admin);

        $this->delete(route('purchases.destroy', $purchase), ['_token' => csrf_token()])
            ->assertRedirect(route('purchases.index'));

        $this->assertDatabaseMissing('tire_purchases', ['id' => $purchase->id]);
    }

    public function test_odometer_is_recorded_once_and_can_be_edited_later(): void
    {
        $unit = $this->createTractor();
        [$tire] = $this->purchaseTires(1, 91000);
        $position = $unit->configuration->positions()->where('is_spare', false)->first();
        $ops = app(TireOperationService::class);
        $ops->execute($unit, [
            'odometer' => 100500,
            'installations' => [['tire_id' => $tire->id, 'position_id' => $position->id]],
        ], $this->admin);
        $ops->execute($unit, [
            'odometer' => 100500,
            'removals' => [['tire_id' => $tire->id]],
        ], $this->admin);

        $this->assertEquals(1, OdometerReading::count());
        $reading = OdometerReading::first();
        $this->assertEquals('VALIDATED', $reading->status->value);

        $this->get(route('odometers.index'))
            ->assertOk()
            ->assertDontSee('Validar')
            ->assertDontSee('pagination.previous')
            ->assertDontSee('pagination.next');

        $this->put(route('odometers.update', $reading), [
            '_token' => csrf_token(),
            'value' => 100400,
            'notes' => 'Corrección',
        ])->assertRedirect(route('odometers.index'));

        $this->assertEquals(100400, $reading->fresh()->value);
        $this->assertEquals(100400, $unit->fresh()->current_odometer);
    }
}
