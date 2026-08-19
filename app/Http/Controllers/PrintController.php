<?php

namespace App\Http\Controllers;

use App\Models\Tire;
use App\Models\WorkOrder;
use App\Support\AccessScope;
use Illuminate\Http\Request;

class PrintController extends Controller
{
    public function tire(Request $request, Tire $tire)
    {
        AccessScope::abortUnlessTire($request->user(), $tire->id);
        $tire->load([
            'brand', 'model', 'size', 'company', 'currentLifecycle',
            'currentLocation.unit', 'currentLocation.position', 'numberChanges.user',
        ]);

        return view('print.tire', $this->printContext($request, [
            'tire' => $tire,
            'companyName' => $tire->company?->name,
        ]));
    }

    public function workOrder(Request $request, WorkOrder $workOrder)
    {
        AccessScope::abortUnlessWorkOrder($request->user(), $workOrder->id);
        $order = $workOrder->load(['tire.model', 'tire.brand', 'shop', 'opener', 'closer', 'company']);

        return view('print.work-order', $this->printContext($request, [
            'order' => $order,
            'companyName' => $order->company?->name,
        ]));
    }

    private function printContext(Request $request, array $payload): array
    {
        return $payload + [
            'issuedAt' => now(),
            'printedBy' => $request->user()?->name,
            'companyName' => $payload['companyName'] ?? $request->user()?->company?->name,
        ];
    }
}
