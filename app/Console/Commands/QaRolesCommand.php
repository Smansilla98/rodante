<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Base;
use App\Models\Fleet;
use App\Models\User;
use App\Support\Qa\QaHttp;
use App\Support\Qa\RoleQaRunner;
use Illuminate\Console\Command;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

class QaRolesCommand extends Command
{
    protected $signature = 'qa:roles';

    protected $description = 'QA completo por rol: altas, bajas, cambios y log por usuario';

    public function handle(): int
    {
        $http = new QaHttp($this->laravel);
        $http->withoutMiddleware(ValidateCsrfToken::class);

        $wanted = [
            'consulta' => UserRole::Consulta,
            'operario' => UserRole::Operario,
            'logistica' => UserRole::Logistica,
            'jefe' => UserRole::JefeSector,
            'admin' => UserRole::Administrador,
        ];

        $users = collect();
        foreach ($wanted as $username => $role) {
            $user = User::query()->where('username', $username)->first();
            if (! $user) {
                $this->error("Falta el usuario {$username}. Corré: php artisan db:seed");

                return self::FAILURE;
            }
            if ($user->role !== $role) {
                $this->warn("{$username} no tiene el rol esperado ({$user->role->label()}).");
            }
            app(\App\Support\Tenancy\TenantContext::class)->setId((int) $user->company_id);
            $user->fleets()->sync(Fleet::pluck('id'));
            $user->bases()->sync(Base::pluck('id'));
            $users->push($user);
        }

        $tag = 'Q'.strtoupper(substr(uniqid(), -7));
        $dir = storage_path('logs/qa/'.$tag);
        $this->info('Corrida '.$tag);
        $this->info('Logs en '.$dir);

        $company = $users->first()->company;
        app(\App\Support\Tenancy\TenantContext::class)->set($company);

        $otherUsers = collect();
        try {
            $provisioned = app(\App\Services\CompanyProvisioningService::class)->provision(
                ['name' => 'QA Empresa B', 'slug' => 'qa-b-'.strtolower(substr($tag, -5))],
                ['name' => 'Admin B', 'username' => 'admin', 'password' => 'Password123a']
            );
            $otherAdmin = $provisioned['admin'];
            $otherAdmin->forceFill(['must_change_password' => false])->save();
            \App\Support\Tenancy\TenantContext::for($provisioned['company'], function () use ($otherAdmin, &$otherUsers) {
                $otherAdmin->fleets()->sync(\App\Models\Fleet::pluck('id'));
                $otherAdmin->bases()->sync(\App\Models\Base::pluck('id'));
            });
            $otherUsers->push($otherAdmin);
        } catch (\Throwable $e) {
            $this->warn('No se pudo armar empresa B para aislamiento: '.$e->getMessage());
        }

        $summary = (new RoleQaRunner($http, $dir, $tag))->run($users, $otherUsers->isNotEmpty() ? $otherUsers : null);

        $this->info("OK {$summary['ok']} · FAIL {$summary['fail']}");
        if ($summary['fail'] > 0) {
            $this->error('Hay pasos fallidos. Ver '.$dir.'/resumen.log');

            return self::FAILURE;
        }

        $this->info('QA completo. Logs: '.$dir);

        return self::SUCCESS;
    }
}
