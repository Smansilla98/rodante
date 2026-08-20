<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class IntegrityAndFieldTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    public function test_integrity_page_is_empty_on_healthy_demo(): void
    {
        [$tire] = $this->purchaseTires(1, 77001);
        $this->get(route('integrity.index'))->assertOk()->assertSee('Sin inconsistencias');
        $this->assertDatabaseHas('tires', ['id' => $tire->id]);
    }

    public function test_integrity_detects_stock_pointing_to_a_unit(): void
    {
        [$tire] = $this->purchaseTires(1, 77002);
        $unit = $this->createTractor();
        $loc = $tire->currentLocation;
        $loc->update(['unit_id' => $unit->id, 'location_kind' => 'STOCK']);

        $this->get(route('integrity.index'))
            ->assertOk()
            ->assertSee('STOCK_ON_UNIT');
    }

    public function test_field_resolves_individual_number(): void
    {
        [$tire] = $this->purchaseTires(1, 77003);
        $this->get(route('field.index', ['q' => (string) $tire->individual_number]))
            ->assertRedirect(route('field.show', $tire));
        $this->get(route('field.show', $tire))->assertOk()->assertSee($tire->displayName());
    }

    public function test_cost_per_km_and_inventory_pages_render(): void
    {
        $this->purchaseTires(1, 77004);
        $this->get(route('reports.cost-km'))->assertOk();
        $this->get(route('reports.inventory'))->assertOk();
    }

    public function test_consulta_cannot_open_integrity(): void
    {
        $consulta = User::factory()->create(['role' => UserRole::Consulta]);
        $this->actingAs($consulta)->get(route('integrity.index'))->assertForbidden();
    }
}
