<?php

namespace App\Http\Controllers\Odometer;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\FleetUnit;
use App\Models\OdometerReading;
use App\Services\OdometerService;
use App\Support\AccessScope;
use Illuminate\Http\Request;

class OdometerController extends Controller
{
    public function index(Request $request)
    {
        $units = FleetUnit::query();
        AccessScope::units($units, $request->user());

        $editing = $request->integer('edit')
            ? OdometerReading::with('unit')->whereIn('unit_id', (clone $units)->select('id'))->find($request->integer('edit'))
            : null;

        return view('odometers.index', [
            'readings' => OdometerReading::with('unit', 'recorder')
                ->whereIn('unit_id', $units->select('id'))
                ->latest('recorded_at')
                ->paginate(40),
            'editing' => $editing,
        ]);
    }

    public function update(Request $request, OdometerReading $reading, OdometerService $odometers)
    {
        AccessScope::abortUnlessUnit($request->user(), (int) $reading->unit_id);
        $data = $request->validate([
            'value' => 'required|integer|min:0',
            'notes' => 'nullable|string|max:255',
        ]);

        try {
            $odometers->update($reading, (int) $data['value'], $request->user(), $data['notes'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['odometer' => $e->getMessage()]);
        }

        return redirect()->route('odometers.index')->with('success', 'Odómetro corregido. La lectura queda actualizada; los km ya asentados en cubiertas no se recalculan.');
    }
}
