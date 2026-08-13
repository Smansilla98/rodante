<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\FleetUnit;
use App\Models\Tire;
use App\Services\IncidentService;
use App\Services\MeasurementService;
use App\Services\ReportService;
use App\Services\RetirementService;
use App\Services\TireOperationService;
use Illuminate\Http\Request;

class TireApiController extends Controller
{
    public function tires(Request $request)
    {
        $tires = Tire::with('brand', 'model', 'size', 'currentLocation')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('individual_number')
            ->paginate(50);

        return response()->json($tires);
    }

    public function show(Tire $tire, ReportService $reports)
    {
        return response()->json($reports->tireHistory($tire));
    }

    public function history(Tire $tire, ReportService $reports)
    {
        $tire = $reports->tireHistory($tire);

        return response()->json([
            'tire' => $tire->only(['id', 'individual_number', 'status', 'condition', 'accumulated_km']),
            'display' => $tire->displayName(),
            'movements' => $tire->movements,
            'incidents' => $tire->incidents,
            'lifecycles' => $tire->lifecycles,
        ]);
    }

    public function units()
    {
        return response()->json(FleetUnit::with('type', 'configuration', 'fleet')->orderBy('plate')->get());
    }

    public function unitLayout(FleetUnit $unit)
    {
        $unit->load('configuration.positions', 'locations.tire.brand', 'locations.tire.model', 'locations.position');

        return response()->json([
            'unit' => $unit,
            'layout' => $unit->configuration->positions->map(fn ($p) => [
                'position' => $p,
                'tire' => $unit->locations->firstWhere('position_id', $p->id)?->tire,
            ]),
        ]);
    }

    public function operate(Request $request, FleetUnit $unit, TireOperationService $operations)
    {
        try {
            $operation = $operations->execute($unit, $request->all(), $request->user());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($operation, 201);
    }

    public function incident(Request $request, Tire $tire, IncidentService $incidents)
    {
        try {
            return response()->json($incidents->register($tire, $request->all(), $request->user()), 201);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function measurement(Request $request, Tire $tire, MeasurementService $measurements)
    {
        try {
            return response()->json($measurements->record($tire, $request->all(), $request->user()), 201);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function retire(Request $request, Tire $tire, RetirementService $retirements)
    {
        try {
            return response()->json($retirements->retire($tire, $request->all(), $request->user()));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
