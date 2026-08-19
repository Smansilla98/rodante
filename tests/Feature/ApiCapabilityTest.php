<?php

namespace Tests\Feature;

use App\Enums\IncidentType;
use App\Enums\UserRole;
use App\Models\Fleet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class ApiCapabilityTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    public function test_consulta_cannot_operate_via_api(): void
    {
        $consulta = User::factory()->create(['role' => UserRole::Consulta]);
        $consulta->fleets()->sync(Fleet::pluck('id'));
        $unit = $this->createTractor();

        Sanctum::actingAs($consulta);
        $this->postJson('/api/v1/units/'.$unit->id.'/tire-operations', [
            'odometer' => 100000,
            'installations' => [],
        ])
            ->assertForbidden();
    }

    public function test_consulta_cannot_register_incident_via_api(): void
    {
        [$tire] = $this->purchaseTires(1, 60001);
        $consulta = User::factory()->create(['role' => UserRole::Consulta]);

        Sanctum::actingAs($consulta);
        $this->postJson('/api/v1/tires/'.$tire->id.'/incident', [
            'type' => IncidentType::Inspeccion->value,
        ])
            ->assertForbidden();
    }

    public function test_consulta_cannot_retire_via_api(): void
    {
        [$tire] = $this->purchaseTires(1, 60002);
        $consulta = User::factory()->create(['role' => UserRole::Consulta]);

        Sanctum::actingAs($consulta);
        $this->postJson('/api/v1/tires/'.$tire->id.'/retire', [
            'reason_id' => 1,
        ])
            ->assertForbidden();
    }

    public function test_consulta_can_read_tires_via_api(): void
    {
        $consulta = User::factory()->create(['role' => UserRole::Consulta]);

        Sanctum::actingAs($consulta);
        $this->getJson('/api/v1/tires')
            ->assertOk();
    }

    public function test_operario_cannot_retire_via_api(): void
    {
        [$tire] = $this->purchaseTires(1, 60003);
        $operario = User::factory()->create(['role' => UserRole::Operario]);

        Sanctum::actingAs($operario);
        $this->postJson('/api/v1/tires/'.$tire->id.'/retire', [
            'reason_id' => 1,
        ])
            ->assertForbidden();
    }
}
