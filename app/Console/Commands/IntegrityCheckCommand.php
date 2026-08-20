<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\IntegrityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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

        Log::warning('Integridad: hallazgos detectados', [
            'count' => $findings->count(),
            'company_id' => $this->option('company') ? (int) $this->option('company') : null,
            'codes' => $findings->pluck('code')->unique()->values()->all(),
            'tire_ids' => $findings->pluck('tire_id')->unique()->values()->all(),
        ]);

        return self::FAILURE;
    }
}
