<?php

namespace App\Http\Controllers\Odometer;

use App\Enums\OdometerStatus;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\OdometerReading;
use App\Services\OdometerService;
use Illuminate\Http\Request;

class OdometerController extends Controller
{
    public function index()
    {
        return view('odometers.index', [
            'readings' => OdometerReading::with('unit', 'recorder', 'validator')
                ->latest()
                ->paginate(40),
        ]);
    }

    public function validateReading(OdometerReading $reading, OdometerService $odometers, Request $request)
    {
        try {
            $odometers->validate($reading, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['odometer' => $e->getMessage()]);
        }

        return back()->with('success', 'Odómetro validado.');
    }

    public function reject(OdometerReading $reading, OdometerService $odometers, Request $request)
    {
        $data = $request->validate(['notes' => 'required|string|max:255']);
        try {
            $odometers->reject($reading, $request->user(), $data['notes']);
        } catch (DomainException $e) {
            return back()->withErrors(['odometer' => $e->getMessage()]);
        }

        return back()->with('success', 'Lectura rechazada. Debe cargarse una corrección nueva.');
    }
}
