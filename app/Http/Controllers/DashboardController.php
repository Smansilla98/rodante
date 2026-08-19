<?php

namespace App\Http\Controllers;

use App\Models\FleetUnit;
use App\Models\OdometerReading;
use App\Services\DashboardService;
use App\Support\AccessScope;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboard)
    {
        $user = $request->user();
        $units = FleetUnit::query();
        AccessScope::units($units, $user);

        return view('dashboard', [
            'stats' => $dashboard->stats($user),
            'recentOdometers' => OdometerReading::with('unit')
                ->whereIn('unit_id', $units->select('id'))
                ->latest('recorded_at')
                ->limit(8)
                ->get(),
        ]);
    }
}
