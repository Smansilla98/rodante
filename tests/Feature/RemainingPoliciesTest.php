<?php

namespace Tests\Feature;

use App\Enums\OdometerStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\OdometerReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class RemainingPoliciesTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    public function test_consulta_receives_403_for_users_abm(): void
    {
        $consulta = User::factory()->create([
            'company_id' => $this->admin->company_id,
            'role' => UserRole::Consulta,
        ]);

        $this->actingAs($consulta)->get(route('users.index'))->assertForbidden();
    }

    public function test_other_company_receives_404_for_unit_show(): void
    {
        $unit = $this->createTractor();
        $company = Company::create([
            'name' => 'Otra empresa',
            'slug' => 'otra-remaining',
            'is_active' => true,
        ]);
        $intruder = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Administrador,
        ]);

        $this->actingAs($intruder)->get(route('units.show', $unit))->assertNotFound();
    }

    public function test_operario_cannot_update_odometer_via_http(): void
    {
        $unit = $this->createTractor();
        $reading = OdometerReading::create([
            'unit_id' => $unit->id,
            'value' => $unit->current_odometer,
            'status' => OdometerStatus::Validated,
            'recorded_by' => $this->admin->id,
            'recorded_at' => now(),
        ]);
        $operario = User::factory()->create([
            'company_id' => $this->admin->company_id,
            'role' => UserRole::Operario,
        ]);
        $operario->fleets()->sync([$unit->fleet_id]);
        $operario->bases()->sync([$unit->base_id]);
        $token = 'remaining-policies-csrf';

        $this->actingAs($operario)
            ->withSession(['_token' => $token])
            ->put(route('odometers.update', $reading), [
                '_token' => $token,
                'value' => $unit->current_odometer + 1,
            ])
            ->assertForbidden();
    }
}
