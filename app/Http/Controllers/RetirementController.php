<?php

namespace App\Http\Controllers;

use App\Enums\TireStatus;
use App\Exceptions\DomainException;
use App\Http\Requests\RetireTireRequest;
use App\Models\MovementReason;
use App\Models\Tire;
use App\Services\RetirementService;
use App\Support\AccessScope;
use Illuminate\Http\Request;

class RetirementController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->role->canRetireOrRecap(), 403);

        $base = Tire::query()
            ->with(['brand', 'model', 'size', 'currentLocation.unit', 'currentLocation.position', 'currentLocation.base', 'openAssignment.unit']);
        AccessScope::tires($base, $request->user());

        $q = trim((string) $request->get('q', ''));

        $eligibleQuery = (clone $base)
            ->where('status', '!=', TireStatus::DeBaja)
            ->whereDoesntHave('openAssignment')
            ->where(function ($query) {
                $query->whereDoesntHave('currentLocation')
                    ->orWhereHas('currentLocation', fn ($loc) => $loc->whereNull('unit_id'));
            })
            ->whereNotIn('status', [TireStatus::Instalada, TireStatus::Auxilio]);

        $blockedQuery = (clone $base)
            ->where('status', '!=', TireStatus::DeBaja)
            ->where(function ($query) {
                $query->whereHas('openAssignment')
                    ->orWhereHas('currentLocation', fn ($loc) => $loc->whereNotNull('unit_id'))
                    ->orWhereIn('status', [TireStatus::Instalada, TireStatus::Auxilio]);
            });

        if ($q !== '') {
            $digits = preg_replace('/\D+/', '', $q);
            $applySearch = function ($query) use ($q, $digits) {
                $query->where(function ($inner) use ($q, $digits) {
                    $inner->where('individual_number', 'like', "%{$q}%")
                        ->orWhereHas('model', fn ($m) => $m->where('code', 'like', "%{$q}%"))
                        ->orWhereHas('currentLocation.unit', fn ($u) => $u->where('plate', 'like', "%{$q}%"));
                    if ($digits) {
                        $inner->orWhere('individual_number', $digits);
                    }
                });
            };
            $applySearch($eligibleQuery);
            $applySearch($blockedQuery);
        }

        $eligible = $eligibleQuery
            ->orderByDesc('accumulated_km')
            ->orderBy('individual_number')
            ->paginate(25, ['*'], 'elegibles')
            ->withQueryString();

        $blocked = $blockedQuery
            ->orderBy('individual_number')
            ->paginate(15, ['*'], 'en_unidad')
            ->withQueryString();

        return view('retirements.index', [
            'eligible' => $eligible,
            'blocked' => $blocked,
            'reasons' => MovementReason::where('applies_to', 'BAJA')->orderBy('name')->get(),
            'q' => $q,
        ]);
    }

    public function store(RetireTireRequest $request, Tire $tire, RetirementService $retirements)
    {
        try {
            $retirements->assertNotMountedOnUnit($tire->load(['openAssignment', 'currentLocation.unit', 'currentLocation.position']));
            $retirements->retire($tire, $request->validated(), $request->user());
        } catch (DomainException $e) {
            return redirect()
                ->route('retirements.index', ['q' => $tire->individual_number])
                ->withErrors(['retire' => $e->getMessage()]);
        }

        return redirect()
            ->route('retirements.index')
            ->with('success', $tire->displayName().' quedó de baja. El historial se conserva.');
    }
}
