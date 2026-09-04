<?php

namespace App\Http\Controllers;

use App\Models\FleetUnit;
use App\Models\Tire;
use App\Support\AccessScope;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $term = trim((string) $request->get('q', ''));
        if ($term === '') {
            return redirect()->route('dashboard');
        }

        [$tires, $units] = $this->hits($request, $term, 20);

        if ($tires->count() === 1 && $units->isEmpty()) {
            return redirect()->route('tires.show', $tires->first());
        }
        if ($units->count() === 1 && $tires->isEmpty()) {
            return redirect()->route('units.show', $units->first());
        }

        return view('search.results', [
            'term' => $term,
            'tires' => $tires,
            'units' => $units,
        ]);
    }

    public function suggest(Request $request)
    {
        $term = trim((string) $request->get('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['items' => []]);
        }

        [$tires, $units] = $this->hits($request, $term, 6);
        $items = collect();
        foreach ($units as $unit) {
            $items->push([
                'type' => 'unit',
                'type_label' => 'Unidad',
                'label' => $unit->plate,
                'hint' => trim(($unit->type?->name ?? '').' · '.($unit->fleet?->name ?? ''), ' ·'),
                'url' => route('units.show', $unit),
            ]);
        }
        foreach ($tires as $tire) {
            $where = $tire->currentLocation?->unit?->plate
                ?? $tire->status?->label()
                ?? '';
            $items->push([
                'type' => 'tire',
                'type_label' => 'Cubierta',
                'label' => $tire->displayName(),
                'hint' => trim(($tire->status?->label() ?? '').($where && $tire->currentLocation?->unit ? ' · '.$where : '')),
                'url' => route('tires.show', $tire),
            ]);
        }

        return response()->json(['items' => $items->take(10)->values()]);
    }

    /**
     * @return array{0: Collection, 1: Collection}
     */
    private function hits(Request $request, string $term, int $limit): array
    {
        $user = $request->user();
        $digits = preg_replace('/\D+/', '', $term) ?: null;
        $plate = mb_strtoupper(preg_replace('/\s+/', '', $term));
        $dot = Tire::normalizeDot($term);

        $tires = Tire::query()->with(['brand', 'model', 'size', 'currentLocation.unit']);
        AccessScope::tires($tires, $user);
        $tires->where(function ($q) use ($term, $digits, $dot) {
            $q->where('individual_number', 'like', "%{$term}%")
                ->orWhereHas('model', fn ($m) => $m->where('code', 'like', "%{$term}%"))
                ->orWhere('public_token', $term);
            if ($digits) {
                $q->orWhere('individual_number', 'like', "%{$digits}%");
            }
            if ($dot) {
                $q->orWhere('dot', 'like', "%{$dot}%");
            }
        })->orderBy('individual_number')->limit($limit);

        $units = FleetUnit::query()->with('type', 'fleet');
        AccessScope::units($units, $user);
        $units->where(function ($q) use ($term, $plate) {
            $q->where('plate', 'like', "%{$term}%");
            if ($plate !== '') {
                $q->orWhereRaw("REPLACE(UPPER(plate), ' ', '') like ?", ['%'.$plate.'%']);
            }
        })->orderBy('plate')->limit($limit);

        return [$tires->get(), $units->get()];
    }
}
