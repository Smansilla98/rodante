<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\TireCondition;
use App\Enums\TireStatus;
use App\Enums\UserRole;
use App\Models\Base;
use App\Models\Tire;
use App\Models\TireBrand;
use App\Models\TireModel;
use App\Models\TireSize;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class OpeningStockTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    public function test_guest_is_redirected_from_starting_point(): void
    {
        auth()->logout();
        $this->app['auth']->forgetGuards();

        $this->get(route('starting-point.index'))->assertRedirect(route('login'));
    }

    public function test_help_starting_point_redirects_to_operational_page(): void
    {
        $this->get(route('help.starting-point'))
            ->assertRedirect(route('starting-point.index'));
    }

    public function test_page_explains_prior_stock_not_purchase(): void
    {
        $this->get(route('starting-point.index'))
            ->assertOk()
            ->assertSee('Punto de partida')
            ->assertSee('Stock real previo')
            ->assertSee('Importar a stock')
            ->assertSee('Numero;Marca;Modelo;Medida')
            ->assertSee('E1_IZQ')
            ->assertDontSee('Importar CSV de stock');
    }

    public function test_consulta_cannot_import_but_can_view(): void
    {
        $consulta = User::factory()->create([
            'role' => UserRole::Consulta,
            'company_id' => $this->admin->company_id,
        ]);

        $this->actingAs($consulta)
            ->get(route('starting-point.index'))
            ->assertOk()
            ->assertSee('rol es de consulta', false)
            ->assertDontSee('Importar a stock');

        $csv = $this->csvFile("Numero;Marca;Modelo;Medida;DOT;Km;Condicion\n90001;Pirelli;FH:01;295/80 R22.5;;0;USADA\n");

        $this->actingAs($consulta)
            ->withSession(['_token' => 'test-csrf-token'])
            ->post(route('starting-point.import-stock'), [
                '_token' => 'test-csrf-token',
                'file' => $csv,
                'base_id' => Base::first()->id,
                'as_of' => now()->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_import_creates_stock_without_purchase(): void
    {
        $brand = TireBrand::where('name', 'Pirelli')->firstOrFail();
        $model = TireModel::where('code', 'FH:01')->firstOrFail();
        $size = TireSize::where('code', '295/80 R22.5')->firstOrFail();
        $this->assertTrue($model->sizes()->whereKey($size->id)->exists());

        $csv = $this->csvFile(
            "Numero;Marca;Modelo;Medida;DOT;Km;Condicion\n".
            "88001;Pirelli;FH:01;295/80 R22.5;9911;12345;USADA\n".
            ";Pirelli;FH:01;295/80 R22.5;;0;NUEVA\n"
        );

        $this->withSession(['_token' => 'test-csrf-token'])
            ->post(route('starting-point.import-stock'), [
                '_token' => 'test-csrf-token',
                'file' => $csv,
                'base_id' => Base::first()->id,
                'as_of' => '2026-01-15',
            ])->assertRedirect(route('starting-point.index'));

        $fixed = Tire::where('individual_number', 88001)->first();
        $this->assertNotNull($fixed);
        $this->assertSame(TireStatus::Stock, $fixed->status);
        $this->assertSame(TireCondition::Usada, $fixed->condition);
        $this->assertSame(12345, (int) $fixed->accumulated_km);
        $this->assertNull($fixed->tire_purchase_item_id);
        $this->assertSame('PUNTO_DE_PARTIDA', $fixed->currentLifecycle?->started_by);
        $this->assertSame(
            MovementType::OpeningIn,
            $fixed->movements()->latest('id')->first()?->type
        );
        $this->assertSame((int) Base::first()->id, (int) $fixed->currentLocation?->base_id);

        $this->assertSame(2, Tire::where('tire_brand_id', $brand->id)->whereNull('tire_purchase_item_id')->where('status', TireStatus::Stock)->count());
    }

    public function test_duplicate_number_is_rejected(): void
    {
        $this->withSession(['_token' => 'test-csrf-token'])
            ->post(route('starting-point.import-stock'), [
                '_token' => 'test-csrf-token',
                'file' => $this->csvFile("Numero;Marca;Modelo;Medida;DOT;Km;Condicion\n88111;Pirelli;FH:01;295/80 R22.5;;0;USADA\n"),
                'base_id' => Base::first()->id,
                'as_of' => now()->toDateString(),
            ])->assertRedirect(route('starting-point.index'));

        $this->from(route('starting-point.index'))
            ->withSession(['_token' => 'test-csrf-token'])
            ->post(route('starting-point.import-stock'), [
                '_token' => 'test-csrf-token',
                'file' => $this->csvFile("Numero;Marca;Modelo;Medida;DOT;Km;Condicion\n88111;Pirelli;FH:01;295/80 R22.5;;100;USADA\n"),
                'base_id' => Base::first()->id,
                'as_of' => now()->toDateString(),
            ])
            ->assertRedirect(route('starting-point.index'))
            ->assertSessionHasErrors('file');
    }

    private function csvFile(string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'opening');
        file_put_contents($path, $contents);

        return new UploadedFile($path, 'stock-previo.csv', 'text/csv', null, true);
    }
}
