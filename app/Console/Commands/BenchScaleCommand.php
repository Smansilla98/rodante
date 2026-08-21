<?php

namespace App\Console\Commands;

use App\Http\Controllers\ExportController;
use App\Models\Tire;
use App\Models\User;
use App\Services\ReportService;
use App\Support\AccessScope;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BenchScaleCommand extends Command
{
    protected $signature = 'rodante:bench-scale {--company=1 : Empresa a medir} {--json : Emitir solamente JSON}';

    protected $description = 'Mide consultas críticas con un volumen grande de neumáticos';

    public function handle(ReportService $reports, ExportController $exports): int
    {
        $user = User::query()
            ->where('company_id', $this->option('company'))
            ->where('role', 'ADMINISTRADOR')
            ->first();
        if (! $user) {
            $this->error('No existe un administrador para la empresa indicada. Cargá la demo o creá uno.');

            return self::FAILURE;
        }

        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        $results = [];

        $results[] = $this->measure('tires_index', $queries, function () use ($user) {
            $query = Tire::with('brand', 'model', 'size', 'currentLocation');
            AccessScope::tires($query, $user);

            return $query->where('status', 'STOCK')->orderBy('individual_number')->paginate(50);
        });
        $results[] = $this->measure('inventory_report', $queries, fn () => $reports->inventory($user));
        $results[] = $this->measure('csv_stream_start', $queries, fn () => $exports->tiresCsv($request));

        $payload = [
            'company_id' => (int) $this->option('company'),
            'tires' => Tire::query()->where('company_id', $this->option('company'))->count(),
            'results' => $results,
        ];
        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['Caso', 'ms', 'consultas aprox.'], array_map(
                fn ($row) => [$row['case'], $row['ms'], $row['queries']],
                $results,
            ));
            $this->line(json_encode($payload, JSON_UNESCAPED_SLASHES));
        }

        return self::SUCCESS;
    }

    private function measure(string $case, int &$queries, callable $callback): array
    {
        $before = $queries;
        $start = hrtime(true);
        $callback();

        return [
            'case' => $case,
            'ms' => round((hrtime(true) - $start) / 1_000_000, 2),
            'queries' => $queries - $before,
        ];
    }
}
