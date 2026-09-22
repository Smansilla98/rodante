<?php

namespace App\Http\Controllers\Api;

use App\Enums\TireCondition;
use App\Enums\TireStatus;
use App\Exceptions\DomainException;
use App\Exceptions\SheetConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RetireTireRequest;
use App\Http\Requests\ReturnTireToStockRequest;
use App\Http\Requests\StoreTireIncidentRequest;
use App\Http\Requests\StoreTireMeasurementRequest;
use App\Http\Requests\UpdateTireRequest;
use App\Models\FleetUnit;
use App\Models\Tire;
use App\Services\IncidentService;
use App\Services\MeasurementService;
use App\Models\UnitPosition;
use App\Services\PositionFitService;
use App\Services\PredictiveWearService;
use App\Services\ReportService;
use App\Services\RetirementService;
use App\Services\TelemetryService;
use App\Services\TireIdentityService;
use App\Services\TireOperationService;
use App\Support\AccessScope;
use Illuminate\Http\Request;

class TireApiController extends Controller
{
    /**
     * Listado con búsqueda global parcial (número, DOT, marca, modelo, medida, patente
     * de la unidad, base) y filtros rápidos por status/condition — misma idea que
     * `positionCandidates`, sin duplicar reglas: solo texto plano sobre columnas/relaciones.
     */
    public function tires(Request $request)
    {
        $query = Tire::with('brand', 'model', 'size', 'currentLocation.unit', 'currentLocation.position', 'currentLocation.base');
        AccessScope::tires($query, $request->user());
        $tires = $query
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->condition, fn ($q, $c) => $q->where('condition', $c))
            ->when($request->tire_size_id, fn ($q, $sizeId) => $q->where('tire_size_id', $sizeId))
            ->when($request->base_id, fn ($q, $baseId) => $q->whereHas(
                'currentLocation',
                fn ($loc) => $loc->where('base_id', $baseId)
            ))
            ->when($request->q, function ($q, $term) {
                $term = trim((string) $term);
                $digits = preg_replace('/\D+/', '', $term);
                $q->where(function ($inner) use ($term, $digits) {
                    $inner->where('individual_number', 'like', "%{$term}%")
                        ->orWhere('dot', 'like', "%{$term}%")
                        ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', "%{$term}%"))
                        ->orWhereHas('model', fn ($m) => $m->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%"))
                        ->orWhereHas('size', fn ($s) => $s->where('code', 'like', "%{$term}%")->orWhere('alias', 'like', "%{$term}%"))
                        ->orWhereHas('currentLocation.unit', fn ($u) => $u->where('plate', 'like', "%{$term}%"))
                        ->orWhereHas('currentLocation.base', fn ($b) => $b->where('name', 'like', "%{$term}%"));
                    if ($digits !== '') {
                        $inner->orWhere('individual_number', $digits);
                    }
                });
            })
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
            'tire' => $tire->only(['id', 'individual_number', 'dot', 'status', 'condition', 'recap_wear', 'display_condition', 'accumulated_km', 'notes', 'tire_brand_id', 'tire_model_id', 'tire_size_id']),
            'display' => $tire->displayName(),
            'timeline' => $reports->timeline($tire),
            'movements' => $tire->movements,
            'incidents' => $tire->incidents,
            'lifecycles' => $tire->lifecycles,
            // MeasurementService::record() exige una lectura por cada zona de la medida
            // (rechaza el guardado si falta alguna). Sin esto, el cliente no tiene forma
            // de saber cuántas zonas pedir ni cómo se llaman (docs/AUDIT_OT_STOCK_RECAPADO.md, INC-09).
            'measurement_zones' => $tire->size?->zones->map(fn ($zone) => [
                'id' => $zone->id,
                'code' => $zone->code,
                'name' => $zone->name,
            ])->values() ?? [],
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
                'id', 'individual_number', 'dot', 'status', 'condition', 'recap_wear', 'display_condition', 'accumulated_km',
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
        $query = FleetUnit::with('type', 'configuration', 'fleet', 'base')->orderBy('plate');
        AccessScope::units($query, $request->user());

        return response()->json($query->get());
    }

    public function unitLayout(Request $request, FleetUnit $unit)
    {
        $this->authorizeVisible('view', $unit);
        $unit->load('type', 'fleet', 'base', 'configuration.positions', 'locations.tire.brand', 'locations.tire.model', 'locations.position');

        return response()->json([
            'unit' => $unit,
            'layout' => $unit->configuration->positions->map(fn ($p) => [
                'position' => $p,
                'tire' => $unit->locations->firstWhere('position_id', $p->id)?->tire,
            ]),
        ]);
    }

    /**
     * Cubiertas de stock compatibles con una posición — mismo criterio de compatibilidad
     * que Unit/UnitController::stockSearch (web), reutilizando PositionFitService para no
     * duplicar reglas de negocio entre backend y clientes.
     */
    public function positionCandidates(Request $request, FleetUnit $unit, UnitPosition $position, PositionFitService $fit)
    {
        $this->authorizeVisible('view', $unit);
        abort_unless((int) $position->unit_configuration_id === (int) $unit->unit_configuration_id, 404);

        $data = $request->validate(['q' => 'nullable|string|max:40']);

        $unit->load('configuration.positions', 'locations.tire');
        $mounted = $unit->locations->firstWhere('position_id', $position->id)?->tire;
        $needed = $fit->neededApplication($mounted, $position);
        $guide = $fit->fitGuide($mounted, $position, $unit);

        $query = Tire::with('brand', 'model', 'size', 'currentLifecycle')->installable()->orderBy('individual_number');
        AccessScope::tires($query, $request->user());
        if ($width = $unit->allowedTireWidth()) {
            $query->whereHas('size', fn ($q) => $q->where('width_mm', $width));
        }
        if ($mounted?->size_id) {
            $query->where('size_id', $mounted->size_id);
        }
        if ($needed) {
            $query->whereHas('model', fn ($m) => $m->whereIn('application', $fit->compatibleApplicationValues($needed)));
        }
        if ($fit->prefersWinter($unit)) {
            $query->whereHas('model', fn ($m) => $m->where('winter_capable', true));
        }
        if ($term = trim((string) ($data['q'] ?? ''))) {
            $digits = preg_replace('/\D+/', '', $term);
            $query->where(function ($inner) use ($term, $digits) {
                $inner->where('individual_number', 'like', "%{$term}%")
                    ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('model', fn ($m) => $m->where('code', 'like', "%{$term}%"));
                if ($digits !== '') {
                    $inner->orWhere('individual_number', $digits);
                }
            });
        }

        $items = $query->limit(30)->get()
            ->filter(fn (Tire $tire) => $fit->canReplace($tire, $position, $unit, $mounted))
            ->map(fn (Tire $tire) => [
                'id' => $tire->id,
                'individual_number' => $tire->individual_number,
                'brand' => $tire->brand?->name,
                'model' => $tire->model?->name,
                'size' => $tire->size?->code,
                'application_label' => $tire->model?->application?->label(),
            ])
            ->values();

        return response()->json([
            'data' => $items,
            'hint' => $guide['application_label']
                ? 'Solo cubiertas compatibles (nomenclatura '.$guide['application_label'].').'
                : 'Cubiertas compatibles con esta ubicación.',
            'size' => $guide['size'],
        ]);
    }

    public function operate(Request $request, FleetUnit $unit, TireOperationService $operations)
    {
        $this->authorizeVisible('view', $unit);
        $this->authorize('operate', $unit);
        $data = $request->validate([
            // Igual que Unit/UnitController::operate() (web): el odómetro no es obligatorio.
            // Si no llega, TireOperationService usa el último odómetro conocido de la unidad
            // motriz acoplada (tractor para semis/tanques/bateas).
            'odometer' => 'nullable|integer|min:0',
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
        $data['odometer_provisional'] = ! isset($data['odometer']);
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

    /**
     * Clasificación manual de desgaste de una cubierta recapada ("Nueva"/"Usada") — a
     * diferencia de Nueva→Usada (que se calcula solo por km recorridos), esto no tiene
     * una regla automática todavía, así que lo decide la persona que la mira.
     */
    public function setRecapWear(Request $request, Tire $tire)
    {
        $this->authorizeVisible('view', $tire);
        abort_unless($request->user()->role->canWrite(), 403, 'No tiene permiso para clasificar cubiertas.');

        if ($tire->condition !== TireCondition::Recapada) {
            return response()->json(['message' => 'Solo se puede clasificar el desgaste de una cubierta recapada.'], 422);
        }

        $data = $request->validate(['recap_wear' => 'required|in:NUEVA,USADA']);
        $tire->update(['recap_wear' => $data['recap_wear']]);

        return response()->json($tire->fresh(['brand', 'model', 'size']));
    }

    /**
     * Modificar neumático (marca/modelo/medida/DOT/condición/observaciones) — reusa
     * UpdateTireRequest tal cual la usa la web (`TireController::update`), misma
     * autorización (`Gate::allows('update', $tire)`, hoy solo Administrador) y mismas
     * reglas, para no duplicar validaciones entre plataformas.
     */
    public function update(UpdateTireRequest $request, Tire $tire, TireIdentityService $identity)
    {
        $data = $request->validated();
        if (! TireCondition::tryFrom($data['condition'])) {
            return response()->json(['message' => 'Condición inválida.'], 422);
        }
        if ((int) $data['individual_number'] !== (int) $tire->individual_number) {
            try {
                $identity->changeNumber(
                    $tire,
                    (int) $data['individual_number'],
                    (string) ($data['number_reason'] ?? ''),
                    $request->user(),
                );
            } catch (DomainException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }
        $tire->update([
            'tire_brand_id' => $data['tire_brand_id'],
            'tire_model_id' => $data['tire_model_id'],
            'tire_size_id' => $data['tire_size_id'],
            'condition' => $data['condition'],
            'recap_wear' => $data['condition'] === TireCondition::Recapada->value ? ($data['recap_wear'] ?? null) : null,
            'dot' => $data['dot'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json($tire->fresh(['brand', 'model', 'size']));
    }

    /**
     * Cambio rápido de condición — a propósito acotado a Nueva/Nueva usada/Usada: son
     * simples columnas sin efectos colaterales. NO incluye Recapada (requiere pasar por
     * una OT de recapado real, `WorkOrderService`) ni "A reparar"/"Baja" (son `status`,
     * no `condition`, y tienen su propio flujo con motivo/auditoría que no hay que saltear).
     */
    public function setCondition(Request $request, Tire $tire)
    {
        $this->authorizeVisible('view', $tire);
        abort_unless($request->user()->role->canWrite(), 403, 'No tiene permiso para cambiar la condición.');

        if ($tire->status === TireStatus::DeBaja) {
            return response()->json(['message' => 'No se puede cambiar la condición de una cubierta de baja.'], 422);
        }

        $data = $request->validate(['condition' => 'required|in:NUEVA,NUEVA_USADA,USADA']);
        $tire->update(['condition' => $data['condition']]);

        return response()->json($tire->fresh(['brand', 'model', 'size']));
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
