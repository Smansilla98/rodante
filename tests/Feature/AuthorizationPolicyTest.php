<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class AuthorizationPolicyTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    public function test_consulta_cannot_write_or_retire_tire(): void
    {
        [$tire] = $this->purchaseTires(1, 92001);
        $consulta = User::factory()->create([
            'role' => UserRole::Consulta,
            'company_id' => $this->admin->company_id,
        ]);
        $consulta->fleets()->sync($this->admin->fleets()->pluck('fleets.id'));
        $consulta->bases()->sync($this->admin->bases()->pluck('bases.id'));

        $this->assertTrue(Gate::forUser($consulta)->allows('view', $tire));
        $this->assertFalse(Gate::forUser($consulta)->allows('write', $tire));
        $this->assertFalse(Gate::forUser($consulta)->allows('retire', $tire));
        $this->assertFalse(Gate::forUser($consulta)->allows('update', $tire));
        $this->assertFalse(Gate::forUser($consulta)->allows('create', WorkOrder::class));
    }

    public function test_operario_can_write_but_not_retire(): void
    {
        [$tire] = $this->purchaseTires(1, 92002);
        $operario = User::factory()->create([
            'role' => UserRole::Operario,
            'company_id' => $this->admin->company_id,
        ]);
        $operario->fleets()->sync($this->admin->fleets()->pluck('fleets.id'));
        $operario->bases()->sync($this->admin->bases()->pluck('bases.id'));

        $this->assertTrue(Gate::forUser($operario)->allows('write', $tire));
        $this->assertFalse(Gate::forUser($operario)->allows('retire', $tire));
        $this->assertFalse(Gate::forUser($operario)->allows('update', $tire));
    }

    public function test_other_company_cannot_view_tire_via_policy_or_http(): void
    {
        [$tire] = $this->purchaseTires(1, 92003);
        $other = Company::create(['name' => 'Otra pol', 'slug' => 'otra-pol', 'is_active' => true]);
        $intruder = User::factory()->create([
            'role' => UserRole::Administrador,
            'company_id' => $other->id,
        ]);

        $this->assertFalse(Gate::forUser($intruder)->allows('view', $tire));
        $this->actingAs($intruder)->get(route('tires.show', $tire))->assertNotFound();
    }

    public function test_operario_incident_form_request_path_works(): void
    {
        [$tire] = $this->purchaseTires(1, 92004);
        $operario = User::factory()->create([
            'role' => UserRole::Operario,
            'company_id' => $this->admin->company_id,
        ]);
        $operario->fleets()->sync($this->admin->fleets()->pluck('fleets.id'));
        $operario->bases()->sync($this->admin->bases()->pluck('bases.id'));

        $token = 'test-csrf-token';
        $this->actingAs($operario)
            ->withSession(['_token' => $token])
            ->post(route('tires.incidents.store', $tire), [
                '_token' => $token,
                'type' => 'PINCHADURA',
                'description' => 'test policy',
            ])
            ->assertRedirect();
    }
}
