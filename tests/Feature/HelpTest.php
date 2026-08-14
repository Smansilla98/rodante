<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class HelpTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    public function test_guest_is_redirected_from_help(): void
    {
        auth()->logout();
        $this->app['auth']->forgetGuards();

        $this->get(route('help.index'))->assertRedirect(route('login'));
        $this->get(route('help.manual'))->assertRedirect(route('login'));
    }

    public function test_admin_sees_role_guide_and_catalog_permission(): void
    {
        $this->get(route('help.index'))
            ->assertOk()
            ->assertSee('Administrador')
            ->assertSee('Catálogo')
            ->assertSee('Administrar')
            ->assertSee('Qué hace cada parte');
    }

    public function test_consulta_sees_read_only_copy(): void
    {
        $consulta = User::factory()->create(['role' => UserRole::Consulta]);

        $this->actingAs($consulta)
            ->get(route('help.index'))
            ->assertOk()
            ->assertSee('Consulta')
            ->assertSee('Solo lectura')
            ->assertSee('No podés')
            ->assertSee('Montar, rotar, retirar o medir cubiertas');
    }

    public function test_manual_renders_from_markdown(): void
    {
        $this->get(route('help.manual'))
            ->assertOk()
            ->assertSee('Manual de uso')
            ->assertSee('planilla')
            ->assertSee('Odómetros');
    }
}
