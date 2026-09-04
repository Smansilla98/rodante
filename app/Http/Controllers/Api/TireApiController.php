<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainException;
use App\Exceptions\SheetConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RetireTireRequest;
use App\Http\Requests\ReturnTireToStockRequest;
use App\Http\Requests\StoreTireIncidentRequest;
use App\Http\Requests\StoreTireMeasurementRequest;
use App\Models\FleetUnit;
use App\Models\Tire;
use App\Services\IncidentService;
use App\Services\MeasurementService;
use App\Services\PredictiveWearService;
use App\Services\ReportService;
use App\Services\RetirementService;
use App\Services\TelemetryService;
use App\Services\TireOperationService;
use App\Support\AccessScope;
use Illuminate\Http\Request;

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
        $this->authorizeVisible('view', $tire);

        return response()->json($reports->tireHistory($tire));
    }

    public function history(Request $request, Tire $tire, ReportService $reports)
    {
        $this->authorizeVisible('view', $tire);
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

    public function prediction(Request $request, Tire $tire, PredictiveWearService $predictive)
    {
        $this->authorizeVisible('view', $tire);
        $tire->load(['brand', 'model', 'measurements.readings.zone']);

        return response()->json($predictive->forecast($tire));
    }

    public function lifeReport(Request $request, Tire $tire, ReportService $reports, PredictiveWearService $predictive, TelemetryService $telemetry)
    {
        $this->authorizeVisible('view', $tire);
        $history = $reports->tireHistory($tire);
        $telemetry->record('tire.life_report', $history, [
            'tire' => $history->auditLabel(),
        ]);

        return response()->json([
            'tire' => $history->only([
                'id', 'individual_number', 'dot', 'status', 'condition', 'accumulated_km',
                'current_tread_min', 'purchased_at', 'retired_at',
            ]),
            'display' => $history->displayName(),
            'manufacture' => $history->manufactureWeekYear(),
            'timeline' => $reports->timeline($tire),
            'forecast' => $predictive->forecast($history),
            'photos' => $history->photos->where('kind', 'RETIRE')->values()->map(fn ($photo) => [
                'id' => $photo->id,
                'kind' => $photo->kind,
                'captured_at' => $photo->captured_at,
                'original_name' => $photo->original_name,
            ]),
            'cost_total' => $history->costEntries->sum('amount'),
        ]);
    }

    public function telemetry(Request $request, TelemetryService $telemetry)
    {
        abort_unless($request->user()->role->canViewTelemetry(), 403);
        $data = $telemetry->dashboard($request->user());

        return response()->json([
            'days' => $data['days'],
            'totals' => $data['totals'],
            'sources' => $data['sources'],
            'events' => $data['events'],
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
        $this->authorizeVisible('view', $unit);
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
        $this->authorizeVisible('view', $unit);
        $this->authorize('operate', $unit);
        $data = $request->validate([
            'odometer' => 'required|integer|min:0',
            'notes' => 'nullable|string',
            'removals' => 'array',
            'removals.*.tire_id' => 'nullable|exists:tires,id',
            'removals.*.reason_id' => 'nullable|exists:movement_reasons,id',
            'removals.*.destination' => 'nullable|string',
            'removals.*.position_id' => 'nullable|exists:unit_positions,id',
            'installations' => 'array',
            'installations.*.tire_id' => 'nullable|exists:tires,id',
            'installations.*.position_id' => 'nullable|exists:unit_positions,id',
            'installations.*.expect_empty' => 'nullable|boolean',
        ]);
        $data['removals'] = collect($data['removals'] ?? [])->filter(fn ($row) => ! empty($row['tire_id']))->values()->all();
        $data['installations'] = collect($data['installations'] ?? [])->filter(fn ($row) => ! empty($row['tire_id']) && ! empty($row['position_id']))->values()->all();

        try {
            $operation = $operations->execute($unit, $data, $request->user());
        } catch (SheetConflictException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($operation, 201);
    }

    public function incident(StoreTireIncidentRequest $request, Tire $tire, IncidentService $incidents)
    {
        try {
            return response()->json($incidents->register($tire, $request->validated(), $request->user()), 201);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function measurement(StoreTireMeasurementRequest $request, Tire $tire, MeasurementService $measurements)
    {
        try {
            return response()->json($measurements->record($tire, $request->validated(), $request->user()), 201);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function returnToStock(ReturnTireToStockRequest $request, Tire $tire, TireOperationService $operations)
    {
        $data = $request->validated();

        try {
            return response()->json($operations->returnToStock(
                $tire,
                $request->user(),
                $data['notes'] ?? null,
                (bool) ($data['as_recap'] ?? false),
            ));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function retire(RetireTireRequest $request, Tire $tire, RetirementService $retirements)
    {
        try {
            return response()->json($retirements->retire($tire, $request->validated(), $request->user()));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
