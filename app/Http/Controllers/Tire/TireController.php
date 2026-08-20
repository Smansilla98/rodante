<?php

namespace App\Http\Controllers\Tire;

use App\Enums\IncidentType;
use App\Enums\TireCondition;
use App\Enums\TireStatus;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RetireTireRequest;
use App\Http\Requests\ReturnTireToStockRequest;
use App\Http\Requests\StoreTireIncidentRequest;
use App\Http\Requests\StoreTireMeasurementRequest;
use App\Http\Requests\UpdateTireRequest;
use App\Models\MovementReason;
use App\Models\Tire;
use App\Models\TireBrand;
use App\Models\TireModel;
use App\Models\TireSize;
use App\Services\IncidentService;
use App\Services\MeasurementService;
use App\Services\ReportService;
use App\Services\RetirementService;
use App\Services\TireIdentityService;
use App\Services\TireOperationService;
use App\Support\AccessScope;
use App\Support\TireProductCatalog;
use Illuminate\Http\Request;

class TireController extends Controller
{
    public function index(Request $request)
    {
        $query = Tire::query()
            ->with(['brand', 'model', 'size', 'currentLocation.unit', 'currentLocation.position', 'currentLocation.base']);
        AccessScope::tires($query, $request->user());

        $tires = $query
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
            ->when($request->queue === 'repair', fn ($q) => $q->where('status', TireStatus::EnReparacion))
            ->when($request->queue === 'tread', fn ($q) => $q
                ->where('status', '!=', TireStatus::DeBaja)
                ->where('current_tread_min', '<=', 4))
            ->when($request->queue === 'retirement', fn ($q) => $q
                ->where('status', '!=', TireStatus::DeBaja)
                ->where(function ($inner) {
                    $inner->where('accumulated_km', '>=', 80000)
                        ->orWhere('current_tread_min', '<=', 4);
                }))
            ->when($request->q, function ($q, $term) {
                $digits = preg_replace('/\D+/', '', $term);
                $q->where(function ($inner) use ($term, $digits) {
                    $inner->where('individual_number', 'like', "%{$term}%")
                        ->orWhereHas('model', fn ($m) => $m->where('code', 'like', "%{$term}%"))
                        ->orWhereHas('currentLocation.unit', fn ($u) => $u->where('plate', 'like', "%{$term}%"));
                    if ($digits) {
                        $inner->orWhere('individual_number', $digits);
                    }
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

    public function show(Tire $tire, ReportService $reports, Request $request)
    {
        $this->authorizeVisible('view', $tire);
        $history = $reports->tireHistory($tire);
        $incidentTypes = collect(IncidentType::cases())
            ->filter(fn (IncidentType $type) => $type !== IncidentType::Recapado || $request->user()->role->canRetireOrRecap())
            ->values();

        return view('tires.show', [
            'tire' => $history,
            'timeline' => $reports->timeline($tire),
            'incidentTypes' => $incidentTypes,
            'retirementReasons' => MovementReason::where('applies_to', 'BAJA')->get(),
            'brands' => TireBrand::orderBy('name')->get(),
            'models' => TireModel::with('brand')->orderBy('code')->get(),
            'sizes' => TireSize::orderBy('code')->get(),
            'conditions' => TireCondition::cases(),
            'numberChanges' => $tire->numberChanges()->with('user')->limit(20)->get(),
        ]);
    }

    public function update(UpdateTireRequest $request, Tire $tire, TireIdentityService $identity)
    {
        $data = $request->validated();
        if (! TireCondition::tryFrom($data['condition'])) {
            return back()->withErrors(['condition' => 'Condición inválida.'])->withInput();
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
                return back()->withErrors(['individual_number' => $e->getMessage(), 'number_reason' => $e->getMessage()])->withInput();
            }
        }
        $tire->update([
            'tire_brand_id' => $data['tire_brand_id'],
            'tire_model_id' => $data['tire_model_id'],
            'tire_size_id' => $data['tire_size_id'],
            'condition' => $data['condition'],
        ]);

        return redirect()->route('tires.show', $tire)->with('success', 'Cubierta actualizada.');
    }

    public function storeIncident(StoreTireIncidentRequest $request, Tire $tire, IncidentService $incidents)
    {
        try {
            $incidents->register($tire, $request->validated(), $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['incident' => $e->getMessage()]);
        }

        return back()->with('success', 'Incidencia registrada.');
    }

    public function storeMeasurement(StoreTireMeasurementRequest $request, Tire $tire, MeasurementService $measurements)
    {
        try {
            $measurement = $measurements->record($tire, $request->validated(), $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['measurement' => $e->getMessage()]);
        }

        $msg = $measurement->raises_alert
            ? 'Medición guardada. Alerta de desgaste irregular.'
            : 'Medición guardada.';

        return back()->with('success', $msg);
    }

    public function retire(RetireTireRequest $request, Tire $tire, RetirementService $retirements)
    {
        try {
            $retirements->retire($tire, $request->validated(), $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['retire' => $e->getMessage()]);
        }

        return back()->with('success', 'Neumático dado de baja. El historial se conserva.');
    }

    public function returnToStock(ReturnTireToStockRequest $request, Tire $tire, TireOperationService $operations)
    {
        $data = $request->validated();

        try {
            $operations->returnToStock($tire, $request->user(), $data['notes'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['stock' => $e->getMessage()]);
        }

        return back()->with('success', 'La cubierta volvió a stock. La reparación (parche) queda en la ficha; no se abrió una vida nueva.');
    }
}
