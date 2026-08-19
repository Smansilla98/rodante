<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Base;
use App\Models\Fleet;
use App\Models\FleetUnit;
use App\Models\Tire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class P2PolishTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    public function test_timezone_is_argentina(): void
    {
        $this->assertSame('America/Argentina/Buenos_Aires', config('app.timezone'));
    }

    public function test_consulta_dashboard_hides_stock_kpi(): void
    {
        $user = User::factory()->create(['role' => UserRole::Consulta, 'username' => 'kpi-consulta']);
        $user->fleets()->sync(Fleet::pluck('id'));
        $user->bases()->sync(Base::pluck('id'));
        $this->actingAs($user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Total de cubiertas')
            ->assertSee('Kilómetros acumulados')
            ->assertDontSee('>En stock<', false);
    }

    public function test_operario_dashboard_shows_repair_not_retired_kpi(): void
    {
        $user = User::factory()->create(['role' => UserRole::Operario, 'username' => 'kpi-operario']);
        $user->fleets()->sync(Fleet::pluck('id'));
        $user->bases()->sync(Base::pluck('id'));
        $this->actingAs($user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('En reparación')
            ->assertSee('>En stock<', false)
            ->assertDontSee('>De baja<', false);
    }

    public function test_tire_and_unit_factories_work_with_catalog(): void
    {
        $tire = Tire::factory()->create();
        $unit = FleetUnit::factory()->create();

        $this->assertDatabaseHas('tires', ['id' => $tire->id]);
        $this->assertDatabaseHas('fleet_units', ['id' => $unit->id]);
    }

    public function test_kilometers_report_is_paginated(): void
    {
        $this->get(route('reports.kilometers'))->assertOk();
    }
}
