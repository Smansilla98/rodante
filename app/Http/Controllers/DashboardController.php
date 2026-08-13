<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Models\OdometerReading;
use App\Enums\OdometerStatus;

class DashboardController extends Controller
{
    public function index(DashboardService $dashboard)
    {
        return view('dashboard', [
            'stats' => $dashboard->stats(),
            'pendingOdometers' => OdometerReading::with('unit', 'recorder')
                ->where('status', OdometerStatus::Pending)
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }
}
