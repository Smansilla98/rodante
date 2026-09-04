<?php

namespace App\Http\Controllers;

use App\Enums\IncidentType;
use App\Models\Base;
use App\Models\Fleet;
use App\Models\FleetUnit;
use App\Models\TireIncident;
use App\Models\TireMeasurement;
use App\Models\UnitCoupling;
use App\Support\AccessScope;
use Illuminate\Http\Request;

class ConsultationController extends Controller
{
    public function measurements(Request $request)
    {
        $query = TireMeasurement::query()
            ->with(['tire.brand', 'tire.model', 'unit.fleet', 'unit.base', 'readings.zone', 'user']);

        $query->whereHas('tire', function ($q) use ($request) {
            AccessScope::tires($q, $request->user());
        });

        $query
            ->when($request->unit_id, fn ($q, $id) => $q->where('unit_id', $id))
            ->when($request->fleet_id, fn ($q, $id) => $q->whereHas('unit', fn ($u) => $u->where('fleet_id', $id)))
            ->when($request->base_id, fn ($q, $id) => $q->whereHas('unit', fn ($u) => $u->where('base_id', $id)))
            ->when($request->from, fn ($q, $d) => $q->whereDate('measured_at', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('measured_at', '<=', $d))
            ->when($request->filled('min_mm'), function ($q) use ($request) {
                $min = (float) $request->input('min_mm');
                $q->whereHas('tire', fn ($t) => $t->where('current_tread_min', '<=', $min));
            })
            ->when($request->boolean('alert'), fn ($q) => $q->where('raises_alert', true));

        return view('consultations.measurements', [
            'measurements' => $query->latest('measured_at')->paginate(40)->withQueryString(),
            'units' => $this->units($request),
            'fleets' => Fleet::orderBy('name')->get(),
            'bases' => Base::orderBy('name')->get(),
        ]);
    }

    public function incidents(Request $request)
    {
        $query = TireIncident::query()
            ->with(['tire.brand', 'tire.model', 'unit.fleet', 'unit.base', 'user']);

        $query->whereHas('tire', function ($q) use ($request) {
            AccessScope::tires($q, $request->user());
        });

        $query
            ->when($request->type, fn ($q, $type) => $q->where('type', $type))
            ->when($request->unit_id, fn ($q, $id) => $q->where('unit_id', $id))
            ->when($request->fleet_id, fn ($q, $id) => $q->whereHas('unit', fn ($u) => $u->where('fleet_id', $id)))
            ->when($request->base_id, fn ($q, $id) => $q->whereHas('unit', fn ($u) => $u->where('base_id', $id)))
            ->when($request->from, fn ($q, $d) => $q->whereDate('occurred_at', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('occurred_at', '<=', $d));

        return view('consultations.incidents', [
            'incidents' => $query->latest('occurred_at')->paginate(40)->withQueryString(),
            'types' => IncidentType::cases(),
            'units' => $this->units($request),
            'fleets' => Fleet::orderBy('name')->get(),
            'bases' => Base::orderBy('name')->get(),
        ]);
    }

    public function couplings(Request $request)
    {
        $query = UnitCoupling::query()
            ->with(['tractor.fleet', 'trailer.fleet', 'user']);

        $unitIds = FleetUnit::query();
        AccessScope::units($unitIds, $request->user());
        $ids = $unitIds->pluck('id');
        $query->where(function ($q) use ($ids) {
            $q->whereIn('tractor_id', $ids)->orWhereIn('trailer_id', $ids);
        });

        $query
            ->when($request->tractor_id, fn ($q, $id) => $q->where('tractor_id', $id))
            ->when($request->trailer_id, fn ($q, $id) => $q->where('trailer_id', $id))
            ->when($request->from, fn ($q, $d) => $q->whereDate('coupled_at', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('coupled_at', '<=', $d))
            ->when($request->status === 'open', fn ($q) => $q->whereNull('uncoupled_at'))
            ->when($request->status === 'closed', fn ($q) => $q->whereNotNull('uncoupled_at'));

        $tractors = FleetUnit::query();
        AccessScope::units($tractors, $request->user());
        $trailers = FleetUnit::query();
        AccessScope::units($trailers, $request->user());

        return view('consultations.couplings', [
            'couplings' => $query->latest('coupled_at')->paginate(40)->withQueryString(),
            'tractors' => $tractors->whereHas('type', fn ($t) => $t->where('is_powered', true))->orderBy('plate')->get(),
            'trailers' => $trailers->whereHas('type', fn ($t) => $t->where('is_powered', false))->orderBy('plate')->get(),
        ]);
    }

    private function units(Request $request)
    {
        $q = FleetUnit::query()->orderBy('plate');
        AccessScope::units($q, $request->user());

        return $q->get();
    }
}
