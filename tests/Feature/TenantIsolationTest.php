<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Base;
use App\Models\Company;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Fleet;
use App\Models\FleetUnit;
use App\Models\Tire;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    private User $adminA;

    private User $adminB;

    private Tire $tireA;

    private Tire $tireB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);
        $this->companyA = Company::demo();

        $provisioning = app(\App\Services\CompanyProvisioningService::class);
        $result = $provisioning->provision(
            ['name' => 'Empresa B', 'slug' => 'empresa-b'],
            ['name' => 'Admin B', 'username' => 'admin', 'password' => 'Password123a']
        );
        $this->companyB = $result['company'];
        $this->adminB = $result['admin'];
        $this->adminB->forceFill(['must_change_password' => false])->save();

        TenantContext::for($this->companyA, function () {
            $this->adminA = User::factory()->create([
                'company_id' => $this->companyA->id,
                'username' => 'admin',
                'role' => UserRole::Administrador,
                'password' => 'password',
            ]);
            $this->tireA = Tire::factory()->create([
                'company_id' => $this->companyA->id,
                'individual_number' => 55001,
            ]);
        });

        TenantContext::for($this->companyB, function () {
            $this->tireB = Tire::factory()->create([
                'company_id' => $this->companyB->id,
                'individual_number' => 55001,
                // Marca visible distinta para assertDontSee en listados.
            ]);
            // Forzar un atributo único en la vista (DOT) si existe.
            if (\Illuminate\Support\Facades\Schema::hasColumn('tires', 'dot')) {
                $this->tireB->update(['dot' => 'B-DOT-9999']);
            }
            Fleet::query()->firstOrCreate(
                ['company_id' => $this->companyB->id, 'code' => 'AXN-COMB'],
                ['name' => 'Flota B', 'is_active' => true]
            );
            Base::query()->firstOrCreate(
                ['company_id' => $this->companyB->id, 'code' => 'SLT'],
                ['name' => 'Base B', 'is_active' => true]
            );
        });
    }

    public function test_admin_a_cannot_see_company_b_tires_by_list_or_id(): void
    {
        $this->actingAs($this->adminA);
        app(TenantContext::class)->set($this->companyA);

        $this->get(route('tires.index'))->assertOk();
        $this->get(route('tires.show', $this->tireB))->assertNotFound();
        $this->assertNull(Tire::query()->find($this->tireB->id));
        $this->assertNotNull(Tire::withoutTenant()->find($this->tireB->id));
    }

    public function test_same_username_plate_base_code_and_tire_number_allowed(): void
    {
        TenantContext::for($this->companyA, function () {
            FleetUnit::factory()->create([
                'company_id' => $this->companyA->id,
                'plate' => 'ABC123',
            ]);
        });
        TenantContext::for($this->companyB, function () {
            FleetUnit::factory()->create([
                'company_id' => $this->companyB->id,
                'plate' => 'ABC123',
            ]);
        });

        $this->assertSame(2, User::query()->where('username', 'admin')->count());
        $this->assertSame(2, Tire::withoutTenant()->where('individual_number', 55001)->count());
        $this->assertSame(2, Base::withoutTenant()->where('code', 'SLT')->count());
        $this->assertSame(2, FleetUnit::withoutTenant()->where('plate', 'ABC123')->count());
    }

    public function test_api_token_of_a_cannot_read_tire_of_b(): void
    {
        $token = $this->adminA->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/tires/'.$this->tireB->id)
            ->assertNotFound();
    }

    public function test_csv_exports_respect_tenant(): void
    {
        $this->actingAs($this->adminA);
        app(TenantContext::class)->set($this->companyA);

        $this->get(route('exports.tires'))->assertOk();
        $this->get(route('exports.audit'))->assertOk();
        $this->get(route('exports.units'))->assertOk();

        $csv = $this->get(route('exports.tires'))->streamedContent();
        $this->assertStringContainsString('55001', $csv);
        // Solo la cubierta de A (mismo número existe en B).
        $this->assertLessThanOrEqual(1, substr_count($csv, '55001'));
    }

    public function test_console_write_without_tenant_throws(): void
    {
        app(TenantContext::class)->clear();
        $this->expectException(\RuntimeException::class);
        Tire::query()->create([
            'individual_number' => 99999,
            'tire_brand_id' => 1,
            'tire_model_id' => 1,
            'tire_size_id' => 1,
            'status' => 'STOCK',
            'condition' => 'NUEVA',
        ]);
    }

    public function test_all_company_models_register_tenant_scope(): void
    {
        $files = File::files(app_path('Models'));
        $missing = [];
        foreach ($files as $file) {
            $class = 'App\\Models\\'.$file->getFilenameWithoutExtension();
            if (! class_exists($class)) {
                continue;
            }
            $model = new $class;
            if (! \Illuminate\Support\Facades\Schema::hasColumn($model->getTable(), 'company_id')) {
                continue;
            }
            if ($class === User::class || $class === Company::class) {
                continue;
            }
            $uses = class_uses_recursive($class);
            if (! isset($uses[BelongsToCompany::class])) {
                $missing[] = $class;
            }
        }
        $this->assertSame([], $missing, 'Modelos con company_id sin BelongsToCompany: '.implode(', ', $missing));
    }
}
