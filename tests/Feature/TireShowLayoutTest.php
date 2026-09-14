<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Base;
use App\Models\Fleet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class TireShowLayoutTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    public function test_ficha_muestra_resumen_acciones_e_historial(): void
    {
        [$tire] = $this->purchaseTires(1, 92001);

        $this->get(route('tires.show', $tire))
            ->assertOk()
            ->assertSee('Situación actual')
            ->assertSee('Qué hacer ahora')
            ->assertSee('Medir el dibujo (mm)')
            ->assertSee('Anotar un problema')
            ->assertSee('Historial de esta cubierta')
            ->assertSee('De más reciente a más antiguo')
            ->assertSee('Guardar medición')
            ->assertSee('Registrar problema');
    }

    public function test_consulta_ve_historial_pero_no_acciones_de_escritura(): void
    {
        [$tire] = $this->purchaseTires(1, 92002);
        $consulta = User::factory()->create([
            'role' => UserRole::Consulta,
            'company_id' => $this->admin->company_id,
        ]);
        $consulta->fleets()->sync(Fleet::pluck('id'));
        $consulta->bases()->sync(Base::pluck('id'));

        $this->actingAs($consulta)
            ->get(route('tires.show', $tire))
            ->assertOk()
            ->assertSee('Historial de esta cubierta')
            ->assertDontSee('Qué hacer ahora')
            ->assertDontSee('Guardar medición')
            ->assertDontSee('Confirmar baja');
    }
}
