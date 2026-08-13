<?php

namespace App\Http\Controllers\Unit;

use App\Enums\TireStatus;
use App\Enums\UnitStatus;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\Base;
use App\Models\Fleet;
use App\Models\FleetUnit;
use App\Models\MovementReason;
use App\Models\Tire;
use App\Models\UnitConfiguration;
use App\Models\UnitType;
use App\Services\ConfigurationChangeService;
use App\Services\CouplingService;
use App\Services\ReportService;
use App\Services\TireOperationService;
use Illuminate\Http\Request;

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
        ]);
        $data['status'] = UnitStatus::Activa->value;
        $data['current_odometer'] = $data['current_odometer'] ?? 0;
        $data['plate'] = strtoupper(trim($data['plate']));
        $unit = FleetUnit::create($data);

        return redirect()->route('units.show', $unit)->with('success', 'Unidad creada.');
    }

    public function show(FleetUnit $unit, ReportService $reports)
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
            'locations.tire.brand', 'locations.tire.model', 'locations.tire.size', 'locations.tire.openAssignment', 'locations.position',
        ]);

        $layout = $unit->tireLayout();

        return view('units.show', [
            'unit' => $unit,
            'sheetUnits' => $unit->sheetUnits(),
            'layout' => $layout,
            'history' => $reports->unitHistory($unit),
            'stockTires' => Tire::with('brand', 'model', 'size')->installable()->orderBy('individual_number')->get(),
            'reasons' => MovementReason::where('applies_to', 'RETIRO')->get(),
            'tractors' => FleetUnit::whereHas('type', fn ($q) => $q->where('has_odometer', true))->orderBy('plate')->get(),
            'trailers' => FleetUnit::whereHas('type', fn ($q) => $q->where('has_odometer', false))->orderBy('plate')->get(),
            'configurations' => UnitConfiguration::where('is_active', true)->orderBy('code')->get(),
            'destinations' => [TireStatus::Stock, TireStatus::Reserva, TireStatus::EnReparacion],
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

    private function formData(): array
    {
        return [
            'fleets' => Fleet::where('is_active', true)->orderBy('name')->get(),
            'bases' => Base::where('is_active', true)->orderBy('name')->get(),
            'types' => UnitType::where('is_active', true)->orderBy('name')->get(),
            'configurations' => UnitConfiguration::where('is_active', true)->orderBy('code')->get(),
        ];
    }
}
