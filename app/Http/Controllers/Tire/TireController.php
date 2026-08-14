<?php

namespace App\Http\Controllers\Tire;

use App\Enums\IncidentType;
use App\Enums\TireCondition;
use App\Enums\TireStatus;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\MovementReason;
use App\Models\Tire;
use App\Models\TireBrand;
use App\Models\TireModel;
use App\Models\TireSize;
use App\Services\IncidentService;
use App\Services\MeasurementService;
use App\Services\ReportService;
use App\Services\RetirementService;
use App\Support\TireProductCatalog;
use Illuminate\Http\Request;

class TireController extends Controller
{
    public function index(Request $request)
    {
        $tires = Tire::query()
            ->with(['brand', 'model', 'size', 'currentLocation.unit', 'currentLocation.position', 'currentLocation.base'])
            ->when($request->brand_id, fn ($q, $id) => $q->where('tire_brand_id', $id))
            ->when($request->model_id, function ($q, $id) use ($request) {
                $q->where('tire_model_id', $id);
                if ($request->brand_id) {
                    $q->whereHas('model', fn ($m) => $m->where('tire_brand_id', $request->brand_id));
                }
            })
            ->when($request->size_id, fn ($q, $id) => $q->where('tire_size_id', $id))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->condition, fn ($q, $condition) => $q->where('condition', $condition))
            ->when($request->q, function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('individual_number', 'like', "%{$term}%")
                        ->orWhereHas('model', fn ($m) => $m->where('code', 'like', "%{$term}%"));
                });
            })
            ->orderBy('individual_number')
            ->paginate(30)
            ->withQueryString();

        return view('tires.index', [
            'tires' => $tires,
            'catalog' => TireProductCatalog::uiPayload(),
            'statuses' => TireStatus::cases(),
            'conditions' => TireCondition::cases(),
        ]);
    }

    public function stock(Request $request)
    {
        $request->merge(['status' => $request->status ?: TireStatus::Stock->value]);

        return $this->index($request);
    }

    public function show(Tire $tire, ReportService $reports)
    {
        return view('tires.show', [
            'tire' => $reports->tireHistory($tire),
            'incidentTypes' => IncidentType::cases(),
            'retirementReasons' => MovementReason::where('applies_to', 'BAJA')->get(),
            'brands' => TireBrand::orderBy('name')->get(),
            'models' => TireModel::with('brand')->orderBy('code')->get(),
            'sizes' => TireSize::orderBy('code')->get(),
            'conditions' => TireCondition::cases(),
        ]);
    }

    public function update(Request $request, Tire $tire)
    {
        $data = $request->validate([
            'individual_number' => 'required|integer|min:1|unique:tires,individual_number,'.$tire->id,
            'tire_brand_id' => 'required|exists:tire_brands,id',
            'tire_model_id' => 'required|exists:tire_models,id',
            'tire_size_id' => 'required|exists:tire_sizes,id',
            'condition' => 'required|string',
        ]);
        $model = TireModel::with('sizes')->findOrFail($data['tire_model_id']);
        if ((int) $model->tire_brand_id !== (int) $data['tire_brand_id']) {
            return back()->withErrors(['tire_model_id' => $model->code.' no pertenece a esa marca.']);
        }
        if (! $model->sizes->contains('id', (int) $data['tire_size_id'])) {
            return back()->withErrors(['tire_size_id' => $model->code.' no se fabrica en esa medida.']);
        }
        if (! TireCondition::tryFrom($data['condition'])) {
            return back()->withErrors(['condition' => 'Condición inválida.']);
        }
        $tire->update($data);

        return redirect()->route('tires.show', $tire)->with('success', 'Cubierta actualizada.');
    }

    public function storeIncident(Request $request, Tire $tire, IncidentService $incidents)
    {
        $data = $request->validate([
            'type' => 'required|string',
            'occurred_at' => 'nullable|date',
            'description' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'odometer' => 'nullable|integer|min:0',
        ]);

        try {
            $incidents->register($tire, $data, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['incident' => $e->getMessage()]);
        }

        return back()->with('success', 'Incidencia registrada.');
    }

    public function storeMeasurement(Request $request, Tire $tire, MeasurementService $measurements)
    {
        $data = $request->validate([
            'measured_at' => 'nullable|date',
            'odometer' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'readings' => 'required|array',
            'readings.*.zone_id' => 'required|exists:measurement_zones,id',
            'readings.*.millimeters' => 'required|numeric|min:0|max:40',
        ]);

        try {
            $measurement = $measurements->record($tire, $data, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['measurement' => $e->getMessage()]);
        }

        $msg = $measurement->raises_alert
            ? 'Medición guardada. Alerta de desgaste irregular.'
            : 'Medición guardada.';

        return back()->with('success', $msg);
    }

    public function retire(Request $request, Tire $tire, RetirementService $retirements)
    {
        $data = $request->validate([
            'reason_id' => 'required|exists:movement_reasons,id',
            'notes' => 'nullable|string',
        ]);

        try {
            $retirements->retire($tire, $data, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['retire' => $e->getMessage()]);
        }

        return back()->with('success', 'Neumático dado de baja. El historial se conserva.');
    }
}
