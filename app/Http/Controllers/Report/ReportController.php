<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FleetUnit;
use App\Models\OdometerReading;
use App\Models\Tire;
use App\Models\TireIncident;
use App\Models\TireMeasurement;
use App\Models\TireOperation;
use App\Models\TirePurchase;
use App\Models\UnitConfigurationChange;
use App\Models\UnitCoupling;
use App\Services\ReportService;
use App\Support\AccessScope;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function kilometers(Request $request, ReportService $reports)
    {
        return view('reports.kilometers', ['tires' => $reports->kilometersByTire($request->user())]);
    }

    public function costPerKm(Request $request, ReportService $reports)
    {
        return view('reports.cost-km', ['tires' => $reports->costPerKm($request->user())]);
    }

    public function inventory(Request $request, ReportService $reports)
    {
        return view('reports.inventory', ['tires' => $reports->inventory($request->user())]);
    }

    public function consumption(Request $request, ReportService $reports)
    {
        return view('reports.consumption', ['rows' => $reports->consumptionByModel($request->user())]);
    }

    public function incidents(Request $request, ReportService $reports)
    {
        return view('reports.incidents', ['rows' => $reports->incidents($request->user())]);
    }

    public function audit(Request $request)
    {
        $query = AuditLog::query();
        AccessScope::auditLogs($query, $request->user());

        return view('reports.audit', [
            'logs' => $query
                ->with([
                    'user',
                    'entity' => function (MorphTo $morphTo) {
                        $morphTo->morphWith([
                            TireOperation::class => ['unit'],
                            UnitConfigurationChange::class => ['unit', 'fromConfiguration', 'toConfiguration'],
                            UnitCoupling::class => ['tractor', 'trailer'],
                            Tire::class => ['model'],
                            TireMeasurement::class => ['tire.model', 'unit'],
                            TireIncident::class => ['tire.model', 'unit'],
                            TirePurchase::class => ['supplier', 'base'],
                            FleetUnit::class => [],
                            OdometerReading::class => ['unit'],
                        ]);
                    },
                ])
                ->latest('created_at')
                ->paginate(50),
        ]);
    }
}
