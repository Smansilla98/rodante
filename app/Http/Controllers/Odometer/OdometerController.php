<?php

namespace App\Http\Controllers\Odometer;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\OdometerReading;
use App\Services\OdometerService;
use Illuminate\Http\Request;

class OdometerController extends Controller
{
    public function index(Request $request)
    {
        $editing = $request->integer('edit')
            ? OdometerReading::with('unit')->find($request->integer('edit'))
            : null;

        return view('odometers.index', [
            'readings' => OdometerReading::with('unit', 'recorder')
                ->latest('recorded_at')
                ->paginate(40),
            'editing' => $editing,
        ]);
    }

    public function update(Request $request, OdometerReading $reading, OdometerService $odometers)
    {
        $data = $request->validate([
            'value' => 'required|integer|min:0',
            'notes' => 'nullable|string|max:255',
        ]);

        try {
            $odometers->update($reading, (int) $data['value'], $request->user(), $data['notes'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['odometer' => $e->getMessage()]);
        }

        return redirect()->route('odometers.index')->with('success', 'Odómetro corregido.');
    }
}
