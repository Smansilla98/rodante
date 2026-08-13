<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\ReportService;

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
            'logs' => AuditLog::with('user')->latest('created_at')->paginate(50),
        ]);
    }
}
