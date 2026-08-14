<?php

namespace App\Http\Controllers\Unit;

use App\Enums\IncidentType;
use App\Enums\TireStatus;
use App\Enums\UnitStatus;
use App\Exceptions\DomainException;
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
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $units = FleetUnit::with('fleet', 'base', 'type', 'configuration', 'currentCouplingAsTractor.trailer', 'currentCouplingAsTrailer.tractor')
            ->when($request->fleet_id, fn ($q, $id) => $q->where('fleet_id', $id))
            ->when($request->q, fn ($q, $term) => $q->where('plate', 'like', "%{$term}%"))
            ->orderBy('plate')
            ->paginate(30)
            ->withQueryString();

        return view('units.index', [
            'units' => $units,
            'fleets' => Fleet::orderBy('name')->get(),
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
        $unit->load([
            'fleet', 'base', 'type', 'configuration.positions',
            'currentCouplingAsTractor.trailer.type',
            'currentCouplingAsTractor.trailer.base',
            'currentCouplingAsTractor.trailer.configuration.positions',
            'currentCouplingAsTractor.trailer.locations.tire.brand',
            'currentCouplingAsTractor.trailer.locations.tire.model',
            'currentCouplingAsTractor.trailer.locations.tire.size',
            'currentCouplingAsTractor.trailer.locations.tire.openAssignment',
            'currentCouplingAsTractor.trailer.locations.position',
            'currentCouplingAsTrailer.tractor.type',
            'currentCouplingAsTrailer.tractor.base',
            'currentCouplingAsTrailer.tractor.configuration.positions',
            'currentCouplingAsTrailer.tractor.locations.tire.brand',
            'currentCouplingAsTrailer.tractor.locations.tire.model',
            'currentCouplingAsTrailer.tractor.locations.tire.size',
            'currentCouplingAsTrailer.tractor.locations.tire.openAssignment',
            'currentCouplingAsTrailer.tractor.locations.position',
            'locations.tire.brand', 'locations.tire.model', 'locations.tire.size.zones',
            'locations.tire.currentLifecycle', 'locations.tire.openAssignment', 'locations.position',
        ]);

        $layout = $unit->tireLayout();
        $stockTires = Tire::with('brand', 'model', 'size', 'currentLifecycle')->installable()->orderBy('individual_number')->get();
        $canOperate = $request->user()->role->canWrite();

        return view('units.show', [
            'unit' => $unit,
            'sheetUnits' => $unit->sheetUnits(),
            'history' => $reports->unitHistory($unit),
            'slotMap' => $canOperate ? $this->slotMap($unit, $layout, $stockTires, $fit) : [],
            'stockTires' => $canOperate
                ? $stockTires->filter(fn (Tire $tire) => $layout->contains(
                    fn (array $slot) => $fit->canMount($tire, $slot['position'], $unit)
                ))->values()
                : collect(),
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
            'tractors' => FleetUnit::whereHas('type', fn ($q) => $q->where('has_odometer', true))->orderBy('plate')->get(),
            'trailers' => FleetUnit::whereHas('type', fn ($q) => $q->where('has_odometer', false))->orderBy('plate')->get(),
            'configurations' => UnitConfiguration::where('is_active', true)->orderBy('code')->get()
                ->filter(fn ($cfg) => $cfg->isCompatibleWith($unit->type))
                ->values(),
        ]);
    }

    public function operate(Request $request, FleetUnit $unit, TireOperationService $operations)
    {
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
            $operations->execute($unit, $data, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['operation' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Operación registrada. El historial quedó asentado.');
    }

    public function rotate(Request $request, FleetUnit $unit, TireOperationService $operations)
    {
        $data = $request->validate([
            'tire_id' => 'required|exists:tires,id',
            'position_id' => 'required|exists:unit_positions,id',
            'odometer' => 'required|integer|min:0',
        ]);

        try {
            $operations->rotate($unit, (int) $data['tire_id'], (int) $data['position_id'], (int) $data['odometer'], $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['rotate' => $e->getMessage()]);
        }

        return back()->with('success', 'Rotación registrada. Los kilómetros del periodo siguen abiertos.');
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
        $data = $request->validate([
            'action' => 'required|in:install,cambio,pinchadura,rotacion,retirar,incidencia,medicion,patron',
            'odometer' => 'required|integer|min:0',
            'position_id' => 'nullable|exists:unit_positions,id',
            'tire_id' => 'nullable|exists:tires,id',
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
            if ($data['action'] === 'patron') {
                $this->slotPatron($unit, $data, $operations, $patterns, $fit, $request->user());
            } else {
                if (empty($data['position_id'])) {
                    throw new DomainException('Elegí una ubicación.');
                }
                $position = UnitPosition::where('unit_configuration_id', $unit->unit_configuration_id)
                    ->where('id', $data['position_id'])
                    ->firstOrFail();

                match ($data['action']) {
                    'install' => $this->slotInstall($unit, $position, $data, $operations, $request->user()),
                    'cambio' => $this->slotCambio($unit, $position, $data, $operations, $incidents, $request->user()),
                    'pinchadura' => $this->slotPinchadura($unit, $position, $data, $operations, $incidents, $request->user()),
                    'rotacion' => $this->slotRotacion($unit, $position, $data, $operations, $request->user()),
                    'retirar' => $this->slotRetirar($unit, $position, $data, $operations, $request->user()),
                    'incidencia' => $this->slotIncidencia($unit, $position, $data, $incidents, $request->user()),
                    'medicion' => $this->slotMedicion($unit, $position, $data, $measurements, $request->user()),
                };
            }
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
        $data = $request->validate([
            'unit_configuration_id' => 'required|exists:unit_configurations,id',
            'reason' => 'required|string|max:160',
            'notes' => 'nullable|string',
        ]);

        try {
            $changes->change($unit, (int) $data['unit_configuration_id'], $data['reason'], $request->user(), $data['notes'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['config' => $e->getMessage()]);
        }

        return back()->with('success', 'Configuración actualizada. Las cubiertas instaladas pasaron a stock.');
    }

    public function updateSpecs(Request $request, FleetUnit $unit)
    {
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

    public function edit(FleetUnit $unit)
    {
        return view('units.edit', $this->formData() + [
            'unit' => $unit->load('type', 'configuration'),
        ]);
    }

    public function update(Request $request, FleetUnit $unit)
    {
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

    public function destroy(FleetUnit $unit)
    {
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

    private function slotMap(FleetUnit $unit, Collection $layout, Collection $stockTires, PositionFitService $fit): array
    {
        $prefix = $unit->type->sheetPrefix();

        return $layout->map(function (array $slot) use ($layout, $stockTires, $fit, $prefix, $unit) {
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
                    'tread' => $tire->current_tread_min !== null ? rtrim(rtrim((string) $tire->current_tread_min, '0'), '.').' mm' : null,
                    'mountedAt' => $tire->openAssignment?->started_at?->format('d/m/Y'),
                    'zones' => ($tire->size?->zones ?? collect())
                        ->map(fn ($zone) => ['id' => $zone->id, 'name' => $zone->name])
                        ->values()
                        ->all(),
                ] : null,
                'stock' => $stockTires
                    ->filter(fn (Tire $candidate) => $fit->canMount($candidate, $position, $unit))
                    ->map(fn (Tire $candidate) => [
                        'id' => $candidate->id,
                        'label' => $candidate->displayName().' · '.$candidate->size->code,
                    ])
                    ->values()
                    ->all(),
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
                        ])
                        ->values()
                        ->all()
                    : [],
            ];
        })->values()->all();
    }

    private function mountedTire(FleetUnit $unit, UnitPosition $position): Tire
    {
        $location = TireCurrentLocation::where('unit_id', $unit->id)
            ->where('position_id', $position->id)
            ->first();
        if (! $location) {
            throw new DomainException('Esa ubicación está vacía.');
        }

        return Tire::findOrFail($location->tire_id);
    }

    private function slotInstall(FleetUnit $unit, UnitPosition $position, array $data, TireOperationService $operations, $user): void
    {
        if (empty($data['tire_id'])) {
            throw new DomainException('Elegí una cubierta de stock para instalar.');
        }

        $operations->execute($unit, [
            'odometer' => (int) $data['odometer'],
            'notes' => $data['notes'] ?? null,
            'installations' => [['tire_id' => (int) $data['tire_id'], 'position_id' => $position->id]],
        ], $user);
    }

    private function slotCambio(
        FleetUnit $unit,
        UnitPosition $position,
        array $data,
        TireOperationService $operations,
        IncidentService $incidents,
        $user,
    ): void {
        if (empty($data['tire_id'])) {
            throw new DomainException('Elegí la cubierta nueva para el cambio.');
        }

        $current = $this->mountedTire($unit, $position);
        $reasonId = MovementReason::where('code', 'RECAMBIO')->value('id');

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
            ]],
            'installations' => [['tire_id' => (int) $data['tire_id'], 'position_id' => $position->id]],
        ], $user);
    }

    private function slotPinchadura(
        FleetUnit $unit,
        UnitPosition $position,
        array $data,
        TireOperationService $operations,
        IncidentService $incidents,
        $user,
    ): void {
        $current = $this->mountedTire($unit, $position);
        $reasonId = MovementReason::where('code', 'PINCHADURA')->value('id');

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
            ]],
        ], $user);
    }

    private function slotRotacion(FleetUnit $unit, UnitPosition $position, array $data, TireOperationService $operations, $user): void
    {
        if (empty($data['to_position_id'])) {
            throw new DomainException('Elegí la ubicación destino para rotar.');
        }

        $current = $this->mountedTire($unit, $position);
        $operations->rotate($unit, $current->id, (int) $data['to_position_id'], (int) $data['odometer'], $user);
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

        $current = $this->mountedTire($unit, $position);
        $operations->execute($unit, [
            'odometer' => (int) $data['odometer'],
            'notes' => $data['notes'] ?? 'Retiro',
            'removals' => [[
                'tire_id' => $current->id,
                'reason_id' => (int) $data['reason_id'],
                'destination' => $destination->value,
            ]],
        ], $user);
    }

    private function slotIncidencia(FleetUnit $unit, UnitPosition $position, array $data, IncidentService $incidents, $user): void
    {
        $type = IncidentType::tryFrom($data['incident_type'] ?? '');
        if (! $type || in_array($type, [IncidentType::Recapado, IncidentType::DesgasteIrregular], true)) {
            throw new DomainException('Elegí un tipo de incidencia válido.');
        }

        $current = $this->mountedTire($unit, $position);
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
        $current = $this->mountedTire($unit, $position);
        $measurements->record($current, [
            'odometer' => (int) $data['odometer'],
            'unit_id' => $unit->id,
            'notes' => $data['notes'] ?? null,
            'readings' => $data['readings'] ?? [],
        ], $user);
    }

    private function formData(): array
    {
        return [
            'fleets' => Fleet::where('is_active', true)->orderBy('name')->get(),
            'bases' => Base::where('is_active', true)->orderBy('name')->get(),
            'types' => UnitType::where('is_active', true)->orderBy('id')->get(),
            'configurations' => UnitConfiguration::where('is_active', true)->orderBy('id')->get(),
        ];
    }
}
