<?php

namespace App\Http\Controllers\Api;

use App\Enums\IncidentType;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\FleetUnit;
use App\Models\Tire;
use App\Services\IncidentService;
use App\Services\MeasurementService;
use App\Services\ReportService;
use App\Services\RetirementService;
use App\Services\TireOperationService;
use App\Support\AccessScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TireApiController extends Controller
{
    public function tires(Request $request)
    {
        $query = Tire::with('brand', 'model', 'size', 'currentLocation');
        AccessScope::tires($query, $request->user());
        $tires = $query
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('individual_number')
            ->paginate(50);

        return response()->json($tires);
    }

    public function show(Request $request, Tire $tire, ReportService $reports)
    {
        AccessScope::abortUnlessTire($request->user(), $tire->id);

        return response()->json($reports->tireHistory($tire));
    }

    public function history(Request $request, Tire $tire, ReportService $reports)
    {
        AccessScope::abortUnlessTire($request->user(), $tire->id);
        $tire = $reports->tireHistory($tire);

        return response()->json([
            'tire' => $tire->only(['id', 'individual_number', 'status', 'condition', 'accumulated_km']),
            'display' => $tire->displayName(),
            'timeline' => $reports->timeline($tire),
            'movements' => $tire->movements,
            'incidents' => $tire->incidents,
            'lifecycles' => $tire->lifecycles,
        ]);
    }

    public function units(Request $request)
    {
        $query = FleetUnit::with('type', 'configuration', 'fleet')->orderBy('plate');
        AccessScope::units($query, $request->user());

        return response()->json($query->get());
    }

    public function unitLayout(Request $request, FleetUnit $unit)
    {
        AccessScope::abortUnlessUnit($request->user(), $unit->id);
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
        AccessScope::abortUnlessUnit($request->user(), $unit->id);
        $data = $request->validate([
            'odometer' => 'required|integer|min:0',
            'notes' => 'nullable|string',
            'removals' => 'array',
            'removals.*.tire_id' => 'nullable|exists:tires,id',
            'removals.*.reason_id' => 'nullable|exists:movement_reasons,id',
            'removals.*.destination' => 'nullable|string',
            'installations' => 'array',
            'installations.*.tire_id' => 'nullable|exists:tires,id',
            'installations.*.position_id' => 'nullable|exists:unit_positions,id',
        ]);
        $data['removals'] = collect($data['removals'] ?? [])->filter(fn ($row) => ! empty($row['tire_id']))->values()->all();
        $data['installations'] = collect($data['installations'] ?? [])->filter(fn ($row) => ! empty($row['tire_id']) && ! empty($row['position_id']))->values()->all();

        try {
            $operation = $operations->execute($unit, $data, $request->user());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($operation, 201);
    }

    public function incident(Request $request, Tire $tire, IncidentService $incidents)
    {
        AccessScope::abortUnlessTire($request->user(), $tire->id);
        $data = $request->validate([
            'type' => ['required', 'string', Rule::enum(IncidentType::class)],
            'occurred_at' => 'nullable|date',
            'description' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'odometer' => 'nullable|integer|min:0',
        ]);

        try {
            return response()->json($incidents->register($tire, $data, $request->user()), 201);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function measurement(Request $request, Tire $tire, MeasurementService $measurements)
    {
        AccessScope::abortUnlessTire($request->user(), $tire->id);
        $data = $request->validate([
            'measured_at' => 'nullable|date',
            'odometer' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'readings' => 'required|array',
            'readings.*.zone_id' => 'required|exists:measurement_zones,id',
            'readings.*.millimeters' => 'required|numeric|min:0|max:40',
        ]);

        try {
            return response()->json($measurements->record($tire, $data, $request->user()), 201);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function returnToStock(Request $request, Tire $tire, TireOperationService $operations)
    {
        AccessScope::abortUnlessTire($request->user(), $tire->id);
        $data = $request->validate([
            'notes' => 'nullable|string|max:255',
        ]);

        try {
            return response()->json($operations->returnToStock($tire, $request->user(), $data['notes'] ?? null));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function retire(Request $request, Tire $tire, RetirementService $retirements)
    {
        AccessScope::abortUnlessTire($request->user(), $tire->id);
        $data = $request->validate([
            'reason_id' => 'required|exists:movement_reasons,id',
            'notes' => 'nullable|string',
        ]);

        try {
            return response()->json($retirements->retire($tire, $data, $request->user()));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
