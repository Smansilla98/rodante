<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FleetUnit;
use App\Models\Tire;
use App\Models\TireIncident;
use App\Models\TireMeasurement;
use App\Models\TireOperation;
use App\Models\TirePurchase;
use App\Models\UnitConfigurationChange;
use App\Models\UnitCoupling;
use App\Services\ReportService;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReportController extends Controller
{
    public function kilometers(ReportService $reports)
    {
        return view('reports.kilometers', ['tires' => $reports->kilometersByTire()]);
    }

    public function consumption(ReportService $reports)
    {
        return view('reports.consumption', ['rows' => $reports->consumptionByModel()]);
    }

    public function incidents(ReportService $reports)
    {
        return view('reports.incidents', ['rows' => $reports->incidents()]);
    }

    public function audit()
    {
        return view('reports.audit', [
            'logs' => AuditLog::query()
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
                            \App\Models\OdometerReading::class => ['unit'],
                        ]);
                    },
                ])
                ->latest('created_at')
                ->paginate(50),
        ]);
    }
}
