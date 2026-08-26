<?php

namespace App\Http\Controllers;

use App\Models\Tire;
use App\Services\PredictiveWearService;
use App\Services\TelemetryService;
use App\Support\AccessScope;
use Illuminate\Http\Request;

class FieldController extends Controller
{
    public function index(Request $request)
    {
        $term = trim((string) $request->get('q', ''));
        $tire = null;
        if ($term !== '') {
            $query = Tire::query()->with(['brand', 'model', 'size', 'currentLocation.unit', 'currentLocation.position', 'currentLocation.base']);
            AccessScope::tires($query, $request->user());
            $digits = preg_replace('/\D+/', '', $term);
            $query->where(function ($q) use ($term, $digits) {
                $q->where('public_token', $term)
                    ->orWhere('individual_number', $term);
                if ($digits !== '') {
                    $q->orWhere('individual_number', $digits);
                }
            });
            $hits = $query->limit(2)->get();
            if ($hits->count() === 1) {
                return redirect()->route('field.show', $hits->first());
            }
            $tire = false;
        }

        return view('field.index', [
            'term' => $term,
            'miss' => $tire === false,
        ]);
    }

    public function show(Request $request, Tire $tire, PredictiveWearService $predictive, TelemetryService $telemetry)
    {
        AccessScope::abortUnlessTire($request->user(), $tire->id);
        $tire->load(['brand', 'model', 'size', 'currentLocation.unit', 'currentLocation.position', 'currentLocation.base', 'measurements.readings.zone']);
        $telemetry->record('field.identify', $tire, [
            'tire' => $tire->auditLabel(),
        ]);

        return view('field.show', [
            'tire' => $tire,
            'forecast' => $predictive->forecast($tire),
        ]);
    }
}
