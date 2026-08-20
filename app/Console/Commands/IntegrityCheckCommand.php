<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\IntegrityService;
use Illuminate\Console\Command;

class IntegrityCheckCommand extends Command
{
    protected $signature = 'rodante:integrity {--company= : Filtrar por company_id}';

    protected $description = 'Detecta inconsistencias de ubicación, assignments y kilómetros.';

    public function handle(IntegrityService $integrity): int
    {
        $user = null;
        if ($this->option('company')) {
            $user = new User([
                'company_id' => (int) $this->option('company'),
                'role' => UserRole::Administrador,
            ]);
        }
        $findings = $integrity->findings($user);
        if ($findings->isEmpty()) {
            $this->info('Sin inconsistencias detectadas.');

            return self::SUCCESS;
        }
        $this->warn($findings->count().' hallazgos.');
        foreach ($findings as $row) {
            $this->line($row['code'].' · '.$row['label'].' · '.$row['message']);
        }

        return self::FAILURE;
    }
}
