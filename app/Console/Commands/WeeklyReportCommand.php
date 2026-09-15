<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use App\Notifications\WeeklyReportNotification;
use App\Services\ReportService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class WeeklyReportCommand extends Command
{
    protected $signature = 'rodante:weekly-report {--company= : Solo esta company_id} {--dry-run : Lista destinatarios sin enviar}';

    protected $description = 'Envía el informe semanal por correo a jefes/admins (o emails configurados).';

    public function handle(ReportService $reports, TenantContext $tenant): int
    {
        if (! config('rodante.weekly_report.enabled')) {
            $this->warn('Informe semanal programado desactivado (RODANTE_WEEKLY_REPORT_ENABLED=false).');

            return self::SUCCESS;
        }

        $from = now()->subWeek()->startOfWeek()->startOfDay();
        $to = now()->subWeek()->endOfWeek()->endOfDay();
        $overrideEmails = config('rodante.weekly_report.emails', []);

        $companies = Company::query()
            ->when($this->option('company'), fn ($q, $id) => $q->whereKey((int) $id))
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($companies->isEmpty()) {
            $this->warn('No hay empresas activas.');

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($companies as $company) {
            $tenant->set($company);

            try {
                $actor = User::query()
                    ->where('company_id', $company->id)
                    ->where('is_active', true)
                    ->whereIn('role', [UserRole::Administrador, UserRole::JefeSector])
                    ->orderBy('id')
                    ->first();

                if (! $actor) {
                    $this->line("Empresa {$company->id}: sin jefe/admin activo, se omite.");

                    continue;
                }

                $report = $reports->weeklyReport($actor, $from, $to);
                $recipients = $overrideEmails !== []
                    ? $overrideEmails
                    : User::query()
                        ->where('company_id', $company->id)
                        ->where('is_active', true)
                        ->whereIn('role', [UserRole::Administrador, UserRole::JefeSector])
                        ->whereNotNull('email')
                        ->where('email', '!=', '')
                        ->pluck('email')
                        ->unique()
                        ->values()
                        ->all();

                if ($recipients === []) {
                    $this->line("Empresa {$company->id}: sin correos destino.");

                    continue;
                }

                $this->info("Empresa {$company->name}: ".count($recipients).' destino(s).');

                if ($this->option('dry-run')) {
                    foreach ($recipients as $email) {
                        $this->line('  · '.$email);
                    }

                    continue;
                }

                foreach ($recipients as $email) {
                    Notification::route('mail', $email)
                        ->notify(new WeeklyReportNotification($report));
                    $sent++;
                }
            } finally {
                $tenant->clear();
            }
        }

        if ($this->option('dry-run')) {
            $this->comment('Dry-run: no se envió correo.');
        } else {
            $this->info("Enviados: {$sent}.");
        }

        return self::SUCCESS;
    }
}
