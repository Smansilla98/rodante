<?php

namespace App\Http\Controllers\Unit;

use App\Enums\IncidentType;
use App\Enums\TireApplication;
use App\Enums\TireStatus;
use App\Enums\UnitStatus;
use App\Exceptions\DomainException;
use App\Exceptions\SheetConflictException;
use App\Http\Controllers\Controller;
use App\Models\Base;
use App\Models\Fleet;
use App\Models\FleetUnit;
use App\Models\MovementReason;
use App\Models\Tire;
use App\Models\TireCurrentLocation;
use App\Models\UnitConfiguration;
use App\Models\UnitCoupling;
use App\Models\UnitPosition;
use App\Models\UnitType;
use App\Services\ConfigurationChangeService;
use App\Services\CouplingService;
use App\Services\IncidentService;
use App\Services\MeasurementService;
use App\Services\PositionFitService;
use App\Services\ReportService;
use App\Services\RotationPatternService;
use App\Services\TireOperationService;
use App\Support\AccessScope;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $units = FleetUnit::with('fleet', 'base', 'type', 'configuration', 'currentCouplingAsTractor.trailer', 'currentCouplingAsTrailer.tractor');
        AccessScope::units($units, $request->user());
        $units = $units
            ->when($request->fleet_id, fn ($q, $id) => $q->where('fleet_id', $id))
            ->when($request->q, fn ($q, $term) => $q->where('plate', 'like', "%{$term}%"))
            ->orderBy('plate')
            ->paginate(30)
            ->withQueryString();

        return view('units.index', [
            'units' => $units,
            'fleets' => tap(Fleet::orderBy('name'), fn ($q) => AccessScope::applyCompany($q, $request->user()))->get(),
        ]);
    }

    public function create()
    {
        return view('units.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'fleet_id' => 'required|exists:fleets,id',
            'base_id' => 'required|exists:bases,id',
            'unit_type_id' => 'required|exists:unit_types,id',
            'unit_configuration_id' => 'required|exists:unit_configurations,id',
            'plate' => 'required|string|max:20|unique:fleet_units,plate',
            'brand' => 'nullable|string|max:40',
            'model_name' => 'nullable|string|max:40',
            'current_odometer' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'specs' => 'nullable|array',
            'specs.capacity_l' => 'nullable|integer|min:0',
            'specs.compartments' => 'nullable|integer|min:0|max:20',
            'specs.material' => 'nullable|string|max:40',
            'specs.product' => 'nullable|string|max:80',
            'specs.suspension' => 'nullable|string|max:40',
            'specs.tire_width' => 'nullable|in:295,385',
        ]);
        $type = UnitType::findOrFail($data['unit_type_id']);
        $configuration = UnitConfiguration::findOrFail($data['unit_configuration_id']);
        if (! $configuration->isCompatibleWith($type)) {
            return back()->withErrors(['unit_configuration_id' => 'Esa configuración no aplica al tipo '.$type->name.'.'])->withInput();
        }
        $data['status'] = UnitStatus::Activa->value;
        $data['current_odometer'] = $type->has_odometer ? ($data['current_odometer'] ?? 0) : 0;
        $data['plate'] = strtoupper(trim($data['plate']));
        if ($type->has_odometer) {
            $data['specs'] = null;
        } else {
            if (empty($data['specs']['tire_width'] ?? null)) {
                return back()->withErrors(['specs.tire_width' => 'En tanque, semi o batea hay que indicar si lleva lineal 295 o 385.'])->withInput();
            }
            $data['specs'] = array_filter($data['specs'] ?? [], fn ($value) => $value !== null && $value !== '');
            $data['specs'] = $data['specs'] ?: null;
        }
        $unit = FleetUnit::create($data);

        return redirect()->route('units.show', $unit)->with('success', 'Unidad creada.');
    }

    public function show(FleetUnit $unit, ReportService $reports, PositionFitService $fit, RotationPatternService $patterns, Request $request)
    {
        $this->authorizeVisible('view', $unit);
        AccessScope::abortUnlessUnit($request->user(), $unit->id);
        $unit->load([
            'fleet', 'base', 'type', 'configuration.positions',
            'currentCouplingAsTractor.trailer.type',
            'currentCouplingAsTractor.trailer.base',
            'currentCouplingAsTractor.trailer.configuration.positions',
            'currentCouplingAsTractor.trailer.locations.tire.brand',
            'currentCouplingAsTractor.trailer.locations.tire.model',
            'currentCouplingAsTractor.trailer.locations.tire.size',
            'currentCouplingAsTractor.trailer.locations.tire.openAssignment.openSegment',
            'currentCouplingAsTractor.trailer.locations.position',
            'currentCouplingAsTrailer.tractor.type',
            'currentCouplingAsTrailer.tractor.base',
            'currentCouplingAsTrailer.tractor.configuration.positions',
            'currentCouplingAsTrailer.tractor.locations.tire.brand',
            'currentCouplingAsTrailer.tractor.locations.tire.model',
            'currentCouplingAsTrailer.tractor.locations.tire.size',
            'currentCouplingAsTrailer.tractor.locations.tire.openAssignment.openSegment',
            'currentCouplingAsTrailer.tractor.locations.position',
            'locations.tire.brand', 'locations.tire.model', 'locations.tire.size.zones',
            'locations.tire.currentLifecycle', 'locations.tire.openAssignment.openSegment', 'locations.position',
        ]);

        $layout = $unit->tireLayout();
        $canOperate = $request->user()->role->canWrite();

        return view('units.show', [
            'unit' => $unit,
            'sheetUnits' => $unit->sheetUnits(),
            'history' => $reports->unitHistory($unit),
            'slotMap' => $canOperate ? $this->slotMap($unit, $layout, $fit) : [],
            'rotationPatterns' => $canOperate ? $patterns->forLayout($layout, $fit, $unit) : [],
            'spareSlotId' => $unit->configuration->positions->firstWhere('is_spare', true)?->id,
            'canOperate' => $canOperate,
            'incidentTypes' => collect(IncidentType::cases())
                ->reject(fn (IncidentType $type) => in_array($type, [
                    IncidentType::Recapado,
                    IncidentType::DesgasteIrregular,
                    IncidentType::Pinchadura,
                    IncidentType::Cambio,
                ], true))
                ->values(),
            'reasons' => MovementReason::where('applies_to', 'RETIRO')->orderBy('name')->get(),
            'destinations' => [TireStatus::Stock, TireStatus::Reserva, TireStatus::EnReparacion],
            'tractors' => tap(FleetUnit::whereHas('type', fn ($q) => $q->where('has_odometer', true))->orderBy('plate'), fn ($q) => AccessScope::units($q, $request->user()))->get(),
            'trailers' => tap(FleetUnit::whereHas('type', fn ($q) => $q->where('has_odometer', false))->orderBy('plate'), fn ($q) => AccessScope::units($q, $request->user()))->get(),
            'configurations' => UnitConfiguration::where('is_active', true)->orderBy('code')->get()
                ->filter(fn ($cfg) => $cfg->isCompatibleWith($unit->type))
                ->values(),
        ]);
    }

    public function operate(Request $request, FleetUnit $unit, TireOperationService $operations)
    {
        $this->authorizeVisible('view', $unit);
        $this->authorize('operate', $unit);
        AccessScope::abortUnlessUnit($request->user(), $unit->id);
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
            $operations->execute($unit, $data, $request->user());
        } catch (SheetConflictException $e) {
            return back()->withErrors(['operation' => $e->getMessage()])->withInput();
        } catch (DomainException $e) {
            return back()->withErrors(['operation' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Operación registrada. El historial quedó asentado.');
    }

    public function stockSearch(Request $request, FleetUnit $unit, PositionFitService $fit)
    {
        AccessScope::abortUnlessUnit($request->user(), $unit->id);
        $data = $request->validate([
            'q' => 'nullable|string|max:40',
            'position_id' => 'nullable|exists:unit_positions,id',
            'tire_id' => 'nullable|exists:tires,id',
        ]);

        $layout = $unit->tireLayout();
        if (! empty($data['tire_id'])) {
            AccessScope::abortUnlessTire($request->user(), (int) $data['tire_id']);
            $tire = Tire::with('brand', 'model', 'size', 'currentLifecycle')->findOrFail($data['tire_id']);
            $positions = $layout
                ->filter(fn (array $slot) => $fit->canMount($tire, $slot['position'], $unit))
                ->map(fn (array $slot) => $slot['position']->id)
                ->values()
                ->all();

            return response()->json(['positions' => $positions]);
        }

        if (empty($data['position_id'])) {
            return response()->json([
                'data' => [],
                'application' => null,
                'hint' => 'Elegí primero la cubierta a cambiar.',
            ]);
        }

        $slot = $layout->first(fn (array $row) => (int) $row['position']->id === (int) $data['position_id']);
        abort_unless($slot, 404);
        $position = $slot['position'];
        $mounted = $slot['tire'] ?? null;
        $needed = $fit->neededApplication($mounted, $position);

        $query = Tire::with('brand', 'model', 'size', 'currentLifecycle')->installable()->orderBy('individual_number');
        AccessScope::tires($query, $request->user());
        if ($width = $unit->allowedTireWidth()) {
            $query->whereHas('size', fn ($q) => $q->where('width_mm', $width));
        }
        if ($needed) {
            $query->whereHas('model', fn ($m) => $m->whereIn('application', [
                $needed->value,
                TireApplication::Mixto->value,
            ]));
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

        $items = $query->limit(80)->get()
            ->filter(fn (Tire $tire) => $fit->canReplace($tire, $position, $unit, $mounted))
            ->map(fn (Tire $tire) => [
                'id' => $tire->id,
                'label' => $tire->displayName().' · '.($tire->model?->application?->label() ?? '').' · '.($tire->size?->code ?? ''),
                'name' => $tire->displayName(),
                'meta' => trim(($tire->model?->application?->label() ?? '').' '.($tire->size?->code ?? '')),
                'application' => $tire->model?->application?->value,
            ])
            ->values();

        $hint = $needed
            ? 'Solo cubiertas de '.$needed->label().'.'
            : 'Cubiertas compatibles con esta ubicación.';

        return response()->json([
            'data' => $items,
            'application' => $needed?->value,
            'application_label' => $needed?->label(),
            'hint' => $hint,
        ]);
    }

    public function slotAction(
        Request $request,
        FleetUnit $unit,
        TireOperationService $operations,
        IncidentService $incidents,
        MeasurementService $measurements,
        RotationPatternService $patterns,
        PositionFitService $fit,
    ) {
        $this->authorizeVisible('view', $unit);
        $this->authorize('operate', $unit);
        AccessScope::abortUnlessUnit($request->user(), $unit->id);
        $mounted = ['cambio', 'pinchadura', 'rotacion', 'retirar', 'incidencia', 'medicion'];
        $data = $request->validate([
            'action' => 'required|in:install,cambio,pinchadura,rotacion,retirar,incidencia,medicion,patron',
            'odometer' => 'required|integer|min:0',
            'position_id' => 'nullable|exists:unit_positions,id',
            'tire_id' => 'nullable|exists:tires,id',
            'expected_tire_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn () => in_array($request->input('action'), $mounted, true)),
                'exists:tires,id',
            ],
            'expected_to_tire_id' => 'nullable|integer|exists:tires,id',
            'to_position_id' => 'nullable|exists:unit_positions,id',
            'pattern' => 'nullable|in:longitudinal,cruzado,diagonal',
            'reason_id' => 'nullable|exists:movement_reasons,id',
            'destination' => 'nullable|string',
            'incident_type' => 'nullable|string',
            'description' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'readings' => 'nullable|array',
            'readings.*.zone_id' => 'nullable|integer',
            'readings.*.millimeters' => 'nullable|numeric|min:0',
        ]);

        try {
            DB::transaction(function () use ($unit, $data, $operations, $incidents, $measurements, $patterns, $fit, $request) {
                FleetUnit::lockForUpdate()->findOrFail($unit->id);
                if ($data['action'] === 'patron') {
                    $this->slotPatron($unit, $data, $operations, $patterns, $fit, $request->user());

                    return;
                }
                if (empty($data['position_id'])) {
                    throw new DomainException('Elegí una ubicación.');
                }
                $position = UnitPosition::where('unit_configuration_id', $unit->unit_configuration_id)
                    ->where('id', $data['position_id'])
                    ->firstOrFail();

                match ($data['action']) {
                    'install' => $this->slotInstall($unit, $position, $data, $operations, $request->user()),
                    'cambio' => $this->slotCambio($unit, $position, $data, $operations, $incidents, $fit, $request->user()),
                    'pinchadura' => $this->slotPinchadura($unit, $position, $data, $operations, $incidents, $request->user()),
                    'rotacion' => $this->slotRotacion($unit, $position, $data, $operations, $request->user()),
                    'retirar' => $this->slotRetirar($unit, $position, $data, $operations, $request->user()),
                    'incidencia' => $this->slotIncidencia($unit, $position, $data, $incidents, $request->user()),
                    'medicion' => $this->slotMedicion($unit, $position, $data, $measurements, $request->user()),
                };
            });
        } catch (SheetConflictException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 409);
            }

            return back()->withErrors(['operation' => $e->getMessage()])->withInput();
        } catch (DomainException $e) {
            return back()->withErrors(['operation' => $e->getMessage()])->withInput();
        }

        $message = match ($data['action']) {
            'install' => 'Cubierta instalada en la ubicación.',
            'cambio' => 'Cambio registrado: se retiró la cubierta y se instaló la nueva.',
            'pinchadura' => 'Pinchadura registrada. La cubierta pasó a reparación.',
            'rotacion' => 'Rotación registrada. Los kilómetros del periodo siguen abiertos.',
            'retirar' => 'Cubierta retirada. La ubicación quedó libre.',
            'incidencia' => 'Incidencia registrada sobre la cubierta.',
            'medicion' => 'Medición de profundidad guardada.',
            'patron' => 'Esquema de rotación aplicado. Los kilómetros del periodo siguen abiertos.',
        };

        return back()->with('success', $message);
    }

    public function couple(Request $request, FleetUnit $unit, CouplingService $couplings)
    {
        $this->authorizeVisible('view', $unit);
        $this->authorize('couple', $unit);
        AccessScope::abortUnlessUnit($request->user(), $unit->id);
        $data = $request->validate([
            'other_unit_id' => 'required|exists:fleet_units,id',
            'odometer' => 'required|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $other = FleetUnit::with('type')->findOrFail($data['other_unit_id']);
        $tractor = $unit->hasOdometer() ? $unit : $other;
        $trailer = $unit->hasOdometer() ? $other : $unit;

        try {
            $couplings->couple($tractor, $trailer, (int) $data['odometer'], $request->user(), $data['notes'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['coupling' => $e->getMessage()]);
        }

        return back()->with('success', 'Acoplamiento registrado.');
    }

    public function uncouple(Request $request, FleetUnit $unit, CouplingService $couplings)
    {
        $this->authorizeVisible('view', $unit);
        $this->authorize('couple', $unit);
        AccessScope::abortUnlessUnit($request->user(), $unit->id);
        $data = $request->validate([
            'odometer' => 'required|integer|min:0',
        ]);
        $coupling = $unit->currentCouplingAsTractor ?: $unit->currentCouplingAsTrailer;
        if (! $coupling) {
            return back()->withErrors(['coupling' => 'La unidad no está acoplada.']);
        }

        try {
            $couplings->uncouple($coupling, (int) $data['odometer'], $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['coupling' => $e->getMessage()]);
        }

        return back()->with('success', 'Desacople registrado. Se cerraron los segmentos de km del acoplado.');
    }

    public function changeConfiguration(Request $request, FleetUnit $unit, ConfigurationChangeService $changes)
    {
        $this->authorizeVisible('view', $unit);
        $this->authorize('configure', $unit);
        AccessScope::abortUnlessUnit($request->user(), $unit->id);
        $data = $request->validate([
            'unit_configuration_id' => 'required|exists:unit_configurations,id',
            'reason' => 'required|string|max:160',
            'notes' => 'nullable|string',
            'odometer' => $unit->hasOdometer() ? 'required|integer|min:0' : 'nullable|integer|min:0',
        ]);

        try {
            $changes->change(
                $unit,
                (int) $data['unit_configuration_id'],
                $data['reason'],
                $request->user(),
                $data['notes'] ?? null,
                isset($data['odometer']) ? (int) $data['odometer'] : null,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['config' => $e->getMessage()]);
        }

        return back()->with('success', 'Configuración actualizada. Las cubiertas instaladas pasaron a stock.');
    }

    public function updateSpecs(Request $request, FleetUnit $unit)
    {
        $this->authorizeVisible('view', $unit);
        $this->authorize('manage', $unit);
        AccessScope::abortUnlessUnit($request->user(), $unit->id);
        if ($unit->hasOdometer()) {
            return back()->withErrors(['specs' => 'La medida lineal aplica a tanque, semi o batea.']);
        }

        $data = $request->validate([
            'tire_width' => 'required|in:295,385',
        ]);
        $specs = $unit->specs ?? [];
        $specs['tire_width'] = (int) $data['tire_width'];
        $unit->update(['specs' => $specs]);

        return back()->with('success', 'La unidad quedó en lineal '.$data['tire_width'].'.');
    }

    public function edit(Request $request, FleetUnit $unit)
    {
        $this->authorizeVisible('view', $unit);
        $this->authorize('manage', $unit);
        AccessScope::abortUnlessUnit($request->user(), $unit->id);

        return view('units.edit', $this->formData() + [
            'unit' => $unit->load('type', 'configuration'),
        ]);
    }

    public function update(Request $request, FleetUnit $unit)
    {
        $this->authorizeVisible('view', $unit);
        $this->authorize('manage', $unit);
        AccessScope::abortUnlessUnit($request->user(), $unit->id);
        $data = $request->validate([
            'fleet_id' => 'required|exists:fleets,id',
            'base_id' => 'required|exists:bases,id',
            'plate' => 'required|string|max:20|unique:fleet_units,plate,'.$unit->id,
            'brand' => 'nullable|string|max:40',
            'model_name' => 'nullable|string|max:40',
            'status' => 'required|in:ACTIVA,INACTIVA,SPARE',
            'notes' => 'nullable|string',
            'specs' => 'nullable|array',
            'specs.capacity_l' => 'nullable|integer|min:0',
            'specs.compartments' => 'nullable|integer|min:0|max:20',
            'specs.material' => 'nullable|string|max:40',
            'specs.product' => 'nullable|string|max:80',
            'specs.suspension' => 'nullable|string|max:40',
            'specs.tire_width' => 'nullable|in:295,385',
        ]);
        $data['plate'] = strtoupper(trim($data['plate']));
        if ($unit->hasOdometer()) {
            $data['specs'] = null;
        } else {
            if (empty($data['specs']['tire_width'] ?? null)) {
                return back()->withErrors(['specs.tire_width' => 'En tanque, semi o batea hay que indicar si lleva lineal 295 o 385.'])->withInput();
            }
            $data['specs'] = array_filter($data['specs'] ?? [], fn ($value) => $value !== null && $value !== '');
            $data['specs'] = $data['specs'] ?: null;
        }
        $unit->update($data);

        return redirect()->route('units.show', $unit)->with('success', 'Unidad actualizada.');
    }

    public function destroy(Request $request, FleetUnit $unit)
    {
        $this->authorizeVisible('view', $unit);
        $this->authorize('manage', $unit);
        AccessScope::abortUnlessUnit($request->user(), $unit->id);
        if ($unit->locations()->exists()) {
            return back()->withErrors(['delete' => 'Retirá las cubiertas antes de eliminar la unidad.']);
        }
        $open = UnitCoupling::query()
            ->where(function ($query) use ($unit) {
                $query->where('tractor_id', $unit->id)->orWhere('trailer_id', $unit->id);
            })
            ->whereNull('uncoupled_at')
            ->exists();
        if ($open) {
            return back()->withErrors(['delete' => 'Desacoplá la unidad antes de eliminarla.']);
        }
        $history = UnitCoupling::query()
            ->where(function ($query) use ($unit) {
                $query->where('tractor_id', $unit->id)->orWhere('trailer_id', $unit->id);
            })
            ->exists();
        if ($history) {
            $unit->update(['status' => UnitStatus::Inactiva]);

            return redirect()->route('units.index')->with('success', 'La unidad tiene historial: quedó inactiva.');
        }
        $unit->delete();

        return redirect()->route('units.index')->with('success', 'Unidad eliminada.');
    }

    private function slotMap(FleetUnit $unit, Collection $layout, PositionFitService $fit): array
    {
        $prefix = $unit->type->sheetPrefix();

        return $layout->map(function (array $slot) use ($layout, $fit, $prefix, $unit) {
            $position = $slot['position'];
            $tire = $slot['tire'];

            return [
                'id' => $position->id,
                'code' => $position->sheetCode($prefix),
                'name' => $position->name,
                'role' => $position->axleRole(),
                'empty' => $tire === null,
                'tire' => $tire ? [
                    'id' => $tire->id,
                    'name' => $tire->displayName(),
                    'url' => route('tires.show', $tire),
                    'brand' => $tire->brand?->name,
                    'code' => $tire->model?->code,
                    'modelName' => $tire->model?->name,
                    'application' => $tire->model?->application?->label(),
                    'size' => $tire->size?->displayName(),
                    'condition' => $tire->condition->label(),
                    'status' => $tire->status->label(),
                    'life' => (int) ($tire->currentLifecycle?->life_number ?? 1),
                    'km' => (int) $tire->accumulated_km,
                    'startOdometer' => $tire->openAssignment?->openSegment?->start_odometer,
                    'tread' => $tire->current_tread_min !== null ? rtrim(rtrim((string) $tire->current_tread_min, '0'), '.').' mm' : null,
                    'mountedAt' => $tire->openAssignment?->started_at?->format('d/m/Y'),
                    'zones' => ($tire->size?->zones ?? collect())
                        ->map(fn ($zone) => ['id' => $zone->id, 'name' => $zone->name])
                        ->values()
                        ->all(),
                ] : null,
                'stock' => [],
                'rotateTo' => $tire
                    ? $layout
                        ->filter(fn (array $other) => $other['position']->id !== $position->id
                            && $fit->canMount($tire, $other['position'], $unit)
                            && ($other['tire'] === null || $fit->canMount($other['tire'], $position, $unit)))
                        ->map(fn (array $other) => [
                            'id' => $other['position']->id,
                            'label' => $other['tire']
                                ? 'Intercambiar · '.$other['position']->sheetCode($prefix).' · '.$other['tire']->displayName()
                                : 'Libre · '.$other['position']->sheetCode($prefix).' · '.$other['position']->axleRole(),
                            'occupied' => $other['tire'] !== null,
                            'tire_id' => $other['tire']?->id,
                        ])
                        ->values()
                        ->all()
                    : [],
            ];
        })->values()->all();
    }

    private function mountedTire(FleetUnit $unit, UnitPosition $position, ?int $expectedTireId = null): Tire
    {
        $location = TireCurrentLocation::where('unit_id', $unit->id)
            ->where('position_id', $position->id)
            ->lockForUpdate()
            ->first();
        if (! $location) {
            throw $expectedTireId ? new SheetConflictException : new DomainException('Esa ubicación está vacía.');
        }
        if ($expectedTireId !== null && (int) $location->tire_id !== $expectedTireId) {
            throw new SheetConflictException;
        }

        return Tire::lockForUpdate()->findOrFail($location->tire_id);
    }

    private function slotInstall(FleetUnit $unit, UnitPosition $position, array $data, TireOperationService $operations, $user): void
    {
        if (empty($data['tire_id'])) {
            throw new DomainException('Elegí una cubierta de stock para instalar.');
        }
        if (TireCurrentLocation::where('unit_id', $unit->id)->where('position_id', $position->id)->exists()) {
            throw new SheetConflictException;
        }

        $operations->execute($unit, [
            'odometer' => (int) $data['odometer'],
            'notes' => $data['notes'] ?? null,
            'installations' => [[
                'tire_id' => (int) $data['tire_id'],
                'position_id' => $position->id,
                'expect_empty' => true,
            ]],
        ], $user);
    }

    private function slotCambio(
        FleetUnit $unit,
        UnitPosition $position,
        array $data,
        TireOperationService $operations,
        IncidentService $incidents,
        PositionFitService $fit,
        $user,
    ): void {
        if (empty($data['tire_id'])) {
            throw new DomainException('Elegí la cubierta nueva para el cambio.');
        }

        $current = $this->mountedTire($unit, $position, isset($data['expected_tire_id']) ? (int) $data['expected_tire_id'] : null);
        $replacement = Tire::with('model', 'size', 'currentLifecycle')->findOrFail((int) $data['tire_id']);
        $fit->assertReplacementFits($replacement, $position, $unit, $current);
        $reasonId = MovementReason::where('code', 'RECAMBIO')->value('id');

        DB::transaction(function () use ($unit, $position, $data, $operations, $incidents, $user, $current, $reasonId) {
            $incidents->register($current, [
                'type' => IncidentType::Cambio->value,
                'odometer' => (int) $data['odometer'],
                'unit_id' => $unit->id,
                'position_id' => $position->id,
                'notes' => $data['notes'] ?? null,
            ], $user);

            $operations->execute($unit, [
                'odometer' => (int) $data['odometer'],
                'notes' => $data['notes'] ?? 'Cambio',
                'removals' => [[
                    'tire_id' => $current->id,
                    'reason_id' => $reasonId,
                    'destination' => TireStatus::Stock->value,
                    'position_id' => $position->id,
                ]],
                'installations' => [[
                    'tire_id' => (int) $data['tire_id'],
                    'position_id' => $position->id,
                    'expect_empty' => true,
                ]],
            ], $user);
        });
    }

    private function slotPinchadura(
        FleetUnit $unit,
        UnitPosition $position,
        array $data,
        TireOperationService $operations,
        IncidentService $incidents,
        $user,
    ): void {
        $current = $this->mountedTire($unit, $position, isset($data['expected_tire_id']) ? (int) $data['expected_tire_id'] : null);
        $reasonId = MovementReason::where('code', 'PINCHADURA')->value('id');

        DB::transaction(function () use ($unit, $position, $data, $operations, $incidents, $user, $current, $reasonId) {
            $incidents->register($current, [
                'type' => IncidentType::Pinchadura->value,
                'odometer' => (int) $data['odometer'],
                'unit_id' => $unit->id,
                'position_id' => $position->id,
                'notes' => $data['notes'] ?? null,
            ], $user);

            $operations->execute($unit, [
                'odometer' => (int) $data['odometer'],
                'notes' => $data['notes'] ?? 'Pinchadura',
                'removals' => [[
                    'tire_id' => $current->id,
                    'reason_id' => $reasonId,
                    'destination' => TireStatus::EnReparacion->value,
                    'position_id' => $position->id,
                ]],
            ], $user);
        });
    }

    private function slotRotacion(FleetUnit $unit, UnitPosition $position, array $data, TireOperationService $operations, $user): void
    {
        if (empty($data['to_position_id'])) {
            throw new DomainException('Elegí la ubicación destino para rotar.');
        }

        $current = $this->mountedTire($unit, $position, isset($data['expected_tire_id']) ? (int) $data['expected_tire_id'] : null);
        $toOccupant = array_key_exists('expected_to_tire_id', $data)
            ? ($data['expected_to_tire_id'] !== null && $data['expected_to_tire_id'] !== '' ? (int) $data['expected_to_tire_id'] : null)
            : false;
        $expect = ['from' => [$current->id => $position->id]];
        if ($toOccupant !== false) {
            $expect['to'] = [(int) $data['to_position_id'] => $toOccupant];
        }
        $operations->rotate($unit, $current->id, (int) $data['to_position_id'], (int) $data['odometer'], $user, $data['notes'] ?? null, $expect);
    }

    private function slotPatron(
        FleetUnit $unit,
        array $data,
        TireOperationService $operations,
        RotationPatternService $patterns,
        PositionFitService $fit,
        $user,
    ): void {
        if (empty($data['pattern'])) {
            throw new DomainException('Elegí un esquema de rotación.');
        }

        $layout = $unit->tireLayout();
        $chosen = collect($patterns->forLayout($layout, $fit, $unit))->firstWhere('code', $data['pattern']);
        if (! $chosen) {
            throw new DomainException('Ese esquema no aplica a esta configuración.');
        }
        if (! $chosen['ready']) {
            throw new DomainException($chosen['blocked'] ?? 'El esquema no se puede aplicar.');
        }

        $operations->applyPattern($unit, $chosen['pairs'], (int) $data['odometer'], $user, $data['notes'] ?? $chosen['name']);
    }

    private function slotRetirar(FleetUnit $unit, UnitPosition $position, array $data, TireOperationService $operations, $user): void
    {
        if (empty($data['reason_id'])) {
            throw new DomainException('Elegí el motivo del retiro.');
        }

        $destination = TireStatus::tryFrom($data['destination'] ?? '');
        if (! $destination || ! in_array($destination, [TireStatus::Stock, TireStatus::Reserva, TireStatus::EnReparacion], true)) {
            throw new DomainException('Elegí el destino del retiro.');
        }

        $current = $this->mountedTire($unit, $position, isset($data['expected_tire_id']) ? (int) $data['expected_tire_id'] : null);
        $operations->execute($unit, [
            'odometer' => (int) $data['odometer'],
            'notes' => $data['notes'] ?? 'Retiro',
            'removals' => [[
                'tire_id' => $current->id,
                'reason_id' => (int) $data['reason_id'],
                'destination' => $destination->value,
                'position_id' => $position->id,
            ]],
        ], $user);
    }

    private function slotIncidencia(FleetUnit $unit, UnitPosition $position, array $data, IncidentService $incidents, $user): void
    {
        $type = IncidentType::tryFrom($data['incident_type'] ?? '');
        if (! $type || in_array($type, [IncidentType::Recapado, IncidentType::DesgasteIrregular], true)) {
            throw new DomainException('Elegí un tipo de incidencia válido.');
        }

        $current = $this->mountedTire($unit, $position, isset($data['expected_tire_id']) ? (int) $data['expected_tire_id'] : null);
        $incidents->register($current, [
            'type' => $type->value,
            'odometer' => (int) $data['odometer'],
            'unit_id' => $unit->id,
            'position_id' => $position->id,
            'description' => $data['description'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], $user);
    }

    private function slotMedicion(FleetUnit $unit, UnitPosition $position, array $data, MeasurementService $measurements, $user): void
    {
        $current = $this->mountedTire($unit, $position, isset($data['expected_tire_id']) ? (int) $data['expected_tire_id'] : null);
        $measurements->record($current, [
            'odometer' => (int) $data['odometer'],
            'unit_id' => $unit->id,
            'notes' => $data['notes'] ?? null,
            'readings' => $data['readings'] ?? [],
        ], $user);
    }

    private function formData(): array
    {
        $user = auth()->user();
        $fleets = Fleet::where('is_active', true)->orderBy('name');
        $bases = Base::where('is_active', true)->orderBy('name');
        if ($user && ! AccessScope::seesEverything($user)) {
            $fleetIds = AccessScope::fleetIds($user);
            $baseIds = AccessScope::visibleBaseIds($user);
            $fleets->whereIn('id', $fleetIds ?: [0]);
            $bases->whereIn('id', $baseIds ?: [0]);
        }

        return [
            'fleets' => $fleets->get(),
            'bases' => $bases->get(),
            'types' => UnitType::where('is_active', true)->orderBy('id')->get(),
            'configurations' => UnitConfiguration::where('is_active', true)->orderBy('id')->get(),
        ];
    }
}
