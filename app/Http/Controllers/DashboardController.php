<?php

namespace App\Http\Controllers;

use App\Models\OdometerReading;
use App\Services\DashboardService;

class DashboardController extends Controller
{
    public function index(DashboardService $dashboard)
    {
        return view('dashboard', [
            'stats' => $dashboard->stats(),
            'recentOdometers' => OdometerReading::with('unit')
                ->latest('recorded_at')
                ->limit(8)
                ->get(),
        ]);
    }
}
