<?php

namespace Tests\Feature;

use App\Enums\TireApplication;
use App\Models\TireModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class TireFilterApplicationTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    public function test_neumaticos_and_stock_filter_by_application_before_brand(): void
    {
        $this->get(route('tires.index'))
            ->assertOk()
            ->assertSee('Todos los tipos')
            ->assertSee('name="application"', false)
            ->assertSee('Dirección');

        $this->get(route('tires.stock'))
            ->assertOk()
            ->assertSee('Todos los tipos')
            ->assertSee('name="application"', false);

        $steer = TireModel::where('application', TireApplication::Direccion)->firstOrFail();
        $drive = TireModel::where('application', TireApplication::Traccion)->firstOrFail();

        $steerTire = $this->purchaseTires(1, 51001, $steer->code, $steer->sizes()->first()->code)[0];
        $driveTire = $this->purchaseTires(1, 51002, $drive->code, $drive->sizes()->first()->code)[0];

        $html = $this->get(route('tires.index', ['application' => TireApplication::Direccion->value]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString((string) $steerTire->individual_number, $html);
        $this->assertStringNotContainsString((string) $driveTire->individual_number, $html);
    }

    public function test_units_csv_is_attachment_not_html(): void
    {
        $response = $this->get(route('exports.units'));
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('Patente', $response->streamedContent());
        $this->assertStringNotContainsString('<html', $response->streamedContent());
    }
}
