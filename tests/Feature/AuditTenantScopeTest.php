<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CouplingService;
use App\Support\AccessScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class AuditTenantScopeTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    public function test_audit_list_hides_other_company_movements(): void
    {
        $tractor = $this->createTractor();
        $trailer = $this->createTrailer();
        app(CouplingService::class)->couple($tractor, $trailer, 100000, $this->admin);

        $other = Company::create(['name' => 'Transportes B', 'slug' => 'transportes-b', 'is_active' => true]);
        $adminB = User::factory()->create([
            'username' => 'admin-b',
            'role' => UserRole::Administrador,
            'company_id' => $other->id,
        ]);

        $this->actingAs($adminB);
        app(AuditService::class)->log('tire.incident', null, null, [
            'type' => 'INSPECCION',
            'tire' => 'Nº 99999 secreto',
            'unit' => 'OTRA999',
        ]);

        $this->actingAs($this->admin)
            ->get(route('reports.audit'))
            ->assertOk()
            ->assertSee($tractor->plate)
            ->assertDontSee('Nº 99999 secreto')
            ->assertDontSee('OTRA999');

        $this->actingAs($adminB)
            ->get(route('reports.audit'))
            ->assertOk()
            ->assertSee('OTRA999')
            ->assertDontSee($tractor->plate);
    }

    public function test_audit_log_id_from_other_company_is_not_visible(): void
    {
        $other = Company::create(['name' => 'Flota X', 'slug' => 'flota-x', 'is_active' => true]);
        $adminB = User::factory()->create([
            'username' => 'admin-x',
            'role' => UserRole::Administrador,
            'company_id' => $other->id,
        ]);

        $this->actingAs($adminB);
        $foreign = app(AuditService::class)->log('purchase.confirmed', null, null, [
            'number' => 'COMPRA-AJENA',
        ]);

        $this->actingAs($this->admin);
        $this->assertFalse(
            AuditLog::query()
                ->tap(fn ($q) => AccessScope::auditLogs($q, $this->admin))
                ->whereKey($foreign->id)
                ->exists()
        );

        $this->expectException(NotFoundHttpException::class);
        AccessScope::abortUnlessAuditLog($this->admin, $foreign->id);
    }

    public function test_new_audit_rows_carry_company_id(): void
    {
        app(AuditService::class)->log('tire.rotated', null, null, ['unit' => 'TST001', 'moves' => 2]);
        $log = AuditLog::query()->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame((int) $this->admin->company_id, (int) $log->company_id);
    }
}
