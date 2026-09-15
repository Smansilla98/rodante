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
        $this->get(route('help.starting-point'))->assertRedirect(route('login'));
    }

    public function test_admin_sees_role_guide_and_catalog_permission(): void
    {
        $this->get(route('help.index'))
            ->assertOk()
            ->assertSee('Administrador')
            ->assertSee('Catálogo')
            ->assertSee('Administrar')
            ->assertSee('Guía de permisos según el rol')
            ->assertSee('Permisos habilitados')
            ->assertSee('Limitaciones del rol')
            ->assertSee('Punto de partida');
    }

    public function test_consulta_sees_read_only_copy(): void
    {
        $consulta = User::factory()->create(['role' => UserRole::Consulta]);

        $this->actingAs($consulta)
            ->get(route('help.index'))
            ->assertOk()
            ->assertSee('Consulta')
            ->assertSee('solo lectura', false)
            ->assertSee('Limitaciones del rol')
            ->assertSee('Montar, rotar, retirar o medir cubiertas');
    }

    public function test_manual_renders_from_markdown(): void
    {
        $this->get(route('help.manual'))
            ->assertOk()
            ->assertSee('Manual de uso')
            ->assertSee('planilla')
            ->assertSee('Odómetros')
            ->assertSee('Punto de partida')
            ->assertSee('Dar de baja')
            ->assertSee('Informe semanal')
            ->assertSee('exportar CSV')
            ->assertSee('enviar por correo')
            ->assertSee('Día a día');
    }

    public function test_sidebar_is_sectioned_for_admin(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Día a día')
            ->assertSee('Cubiertas')
            ->assertSee('Seguimiento')
            ->assertSee('Informes')
            ->assertSee('Costos')
            ->assertSee('Control')
            ->assertSee('Dar de baja')
            ->assertSee('Informe semanal')
            ->assertSee('Buscar cubierta')
            ->assertSee('data-sb-group', false)
            ->assertSee('sb-lbl--toggle', false);
    }

    public function test_help_starting_point_redirects_to_operation(): void
    {
        $this->get(route('help.starting-point'))
            ->assertRedirect(route('starting-point.index'));
    }
}
