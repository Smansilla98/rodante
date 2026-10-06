<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Base;
use App\Models\Company;
use App\Models\Fleet;
use App\Models\UnitConfiguration;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class MobileParityApiTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    public function test_admin_can_create_update_and_remove_a_unit_via_api(): void
    {
        Sanctum::actingAs($this->admin);
        $type = UnitType::where('code', 'TRACTOR')->firstOrFail();
        $configuration = UnitConfiguration::where('code', '6X4')->firstOrFail();

        $created = $this->postJson('/api/v1/units', [
            'fleet_id' => Fleet::first()->id,
            'base_id' => Base::first()->id,
            'unit_type_id' => $type->id,
            'unit_configuration_id' => $configuration->id,
            'plate' => 'api 123',
            'brand' => 'Scania',
            'current_odometer' => 1500,
        ])->assertCreated()
            ->assertJsonPath('plate', 'API123')
            ->json();

        $this->putJson('/api/v1/units/'.$created['id'], [
            'fleet_id' => Fleet::first()->id,
            'base_id' => Base::first()->id,
            'plate' => 'API123',
            'status' => 'INACTIVA',
            'brand' => 'Scania',
        ])->assertOk()->assertJsonPath('status', 'INACTIVA');

        $this->deleteJson('/api/v1/units/'.$created['id'])
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('fleet_units', ['id' => $created['id']]);
    }

    public function test_consulta_cannot_create_units_and_operario_cannot_edit_them(): void
    {
        $unit = $this->createTractor();
        $consulta = User::factory()->create(['role' => UserRole::Consulta, 'company_id' => $this->admin->company_id]);
        $operario = User::factory()->create(['role' => UserRole::Operario, 'company_id' => $this->admin->company_id]);

        Sanctum::actingAs($consulta);
        $this->postJson('/api/v1/units', ['plate' => 'X'])->assertForbidden();

        Sanctum::actingAs($operario);
        $this->putJson('/api/v1/units/'.$unit->id, [
            'fleet_id' => $unit->fleet_id,
            'base_id' => $unit->base_id,
            'plate' => $unit->plate,
            'status' => 'INACTIVA',
        ])->assertForbidden();
    }

    public function test_other_company_cannot_read_or_change_units_tires_or_users(): void
    {
        $unit = $this->createTractor();
        [$tire] = $this->purchaseTires(1, 91001);
        $colleague = User::factory()->create([
            'username' => 'colega-a',
            'role' => UserRole::Operario,
            'company_id' => $this->admin->company_id,
        ]);

        $other = Company::create(['name' => 'Empresa B', 'slug' => 'empresa-b', 'is_active' => true]);
        $intruder = User::factory()->create([
            'username' => 'admin-b',
            'role' => UserRole::Administrador,
            'company_id' => $other->id,
        ]);

        Sanctum::actingAs($intruder);
        $this->getJson('/api/v1/units/'.$unit->id.'/layout')->assertNotFound();
        $this->getJson('/api/v1/units/'.$unit->id.'/history')->assertNotFound();
        $this->getJson('/api/v1/tires/'.$tire->id)->assertNotFound();
        $this->putJson('/api/v1/users/'.$colleague->id, [
            'name' => 'Hack',
            'username' => 'colega-a',
            'role' => 'ADMINISTRADOR',
            'is_active' => true,
        ])->assertNotFound();

        $this->getJson('/api/v1/search?q='.$unit->plate)
            ->assertOk()
            ->assertJsonPath('units', []);
        $this->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonMissing(['id' => $colleague->id]);
    }

    public function test_search_finds_a_tire_and_a_unit_inside_the_company(): void
    {
        $unit = $this->createTractor();
        [$tire] = $this->purchaseTires(1, 91050);
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/search?q='.$unit->plate)
            ->assertOk()
            ->assertJsonFragment(['id' => $unit->id, 'plate' => $unit->plate]);

        $this->getJson('/api/v1/search?q=91050')
            ->assertOk()
            ->assertJsonFragment(['id' => $tire->id, 'individual_number' => 91050]);

        $this->getJson('/api/v1/units/'.$unit->id.'/history')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonStructure(['units_total', 'tires_by_condition', 'recap_in_shop']);
    }

    public function test_admin_can_create_a_user_for_the_same_company(): void
    {
        Sanctum::actingAs($this->admin);
        $fleetId = Fleet::first()->id;

        $this->postJson('/api/v1/users', [
            'name' => 'Campo Uno',
            'username' => 'campo1',
            'password' => 'Campo12345',
            'role' => 'OPERARIO',
            'fleet_ids' => [$fleetId],
            'base_ids' => [Base::first()->id],
        ])->assertCreated()
            ->assertJsonPath('username', 'campo1')
            ->assertJsonMissingPath('password');

        $this->assertDatabaseHas('users', [
            'username' => 'campo1',
            'company_id' => $this->admin->company_id,
        ]);
    }
}
