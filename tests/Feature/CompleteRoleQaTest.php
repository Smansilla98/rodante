<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Base;
use App\Models\Fleet;
use App\Models\User;
use App\Support\Qa\RoleQaRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class CompleteRoleQaTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    public function test_full_qa_runs_for_every_role_and_writes_logs(): void
    {
        $users = collect();
        foreach ([
            ['qa-consulta', UserRole::Consulta],
            ['qa-operario', UserRole::Operario],
            ['qa-logistica', UserRole::Logistica],
            ['qa-jefe', UserRole::JefeSector],
            ['qa-admin', UserRole::Administrador],
        ] as [$username, $role]) {
            $user = User::factory()->create([
                'username' => $username,
                'name' => $role->label().' QA',
                'role' => $role,
            ]);
            $user->fleets()->sync(Fleet::pluck('id'));
            $user->bases()->sync(Base::pluck('id'));
            $users->push($user);
        }

        $tag = 'T'.strtoupper(substr(uniqid(), -7));
        $dir = storage_path('logs/qa/'.$tag);
        $summary = (new RoleQaRunner($this, $dir, $tag))->run($users);

        $this->assertFileExists($dir.'/qa-consulta.log');
        $this->assertFileExists($dir.'/qa-operario.log');
        $this->assertFileExists($dir.'/qa-logistica.log');
        $this->assertFileExists($dir.'/qa-jefe.log');
        $this->assertFileExists($dir.'/qa-admin.log');
        $this->assertFileExists($dir.'/resumen.json');

        $fails = array_values(array_filter($summary['steps'], fn (array $step) => ! $step['ok']));
        $this->assertSame(
            [],
            array_map(fn (array $step) => $step['user'].' · '.$step['action'].' → '.$step['status'].' '.$step['detail'], $fails),
            'QA por rol dejó pasos fallidos. Ver '.$dir.'/resumen.log',
        );
        $this->assertGreaterThan(80, $summary['ok']);
    }
}
