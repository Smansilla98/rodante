<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FleetUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class TenantRouteBindingTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    public function test_authenticated_user_can_open_own_unit_by_id(): void
    {
        $this->seedDomain();
        $unit = $this->createTractor();

        // Simula request HTTP limpia: el tenant solo lo pone el middleware.
        app(\App\Support\Tenancy\TenantContext::class)->clear();

        $this->actingAs($this->admin)
            ->get(route('units.show', $unit))
            ->assertOk()
            ->assertSee($unit->plate, false);
    }

    public function test_other_company_unit_returns_404_not_403(): void
    {
        $this->seedDomain();
        $unit = $this->createTractor();

        $other = \App\Models\Company::query()->create([
            'name' => 'Otra',
            'slug' => 'otra-bind',
            'is_active' => true,
        ]);
        $intruder = User::factory()->create([
            'company_id' => $other->id,
            'role' => UserRole::Administrador,
            'username' => 'intruso-bind',
        ]);

        $this->actingAs($intruder)
            ->get(route('units.show', $unit))
            ->assertNotFound();
    }
}
