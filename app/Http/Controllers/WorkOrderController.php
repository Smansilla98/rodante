<?php

namespace App\Http\Controllers;

use App\Enums\WorkOrderType;
use App\Exceptions\DomainException;
use App\Models\RetreadShop;
use App\Models\Tire;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use App\Support\AccessScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = WorkOrder::with('tire.model', 'tire.brand', 'shop')->latest();
        AccessScope::workOrders($query, $request->user());

        return view('work-orders.index', [
            'orders' => $query->paginate(30),
        ]);
    }

    public function create(Request $request)
    {
        $tires = Tire::query()->orderBy('individual_number');
        AccessScope::tires($tires, $request->user());
        $shops = RetreadShop::query()->where('is_active', true)->orderBy('name');
        AccessScope::applyCompany($shops, $request->user());

        return view('work-orders.create', [
            'tires' => $tires->whereIn('status', ['STOCK', 'EN_REPARACION', 'RESERVA'])->limit(200)->get(),
            'shops' => $shops->get(),
            'types' => WorkOrderType::cases(),
        ]);
    }

    public function store(Request $request, WorkOrderService $service)
    {
        $data = $request->validate([
            'tire_id' => 'required|exists:tires,id',
            'retread_shop_id' => 'required|exists:retread_shops,id',
            'type' => ['required', Rule::enum(WorkOrderType::class)],
            'notes' => 'nullable|string',
        ]);
        AccessScope::abortUnlessTire($request->user(), (int) $data['tire_id']);

        try {
            $order = $service->open(
                $request->user(),
                Tire::findOrFail($data['tire_id']),
                RetreadShop::findOrFail($data['retread_shop_id']),
                WorkOrderType::from($data['type']),
                $data['notes'] ?? null,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }

        return redirect()->route('work-orders.show', $order)->with('success', 'Orden '.$order->number.' creada.');
    }

    public function show(Request $request, WorkOrder $workOrder)
    {
        AccessScope::abortUnlessWorkOrder($request->user(), $workOrder->id);

        return view('work-orders.show', [
            'order' => $workOrder->load('tire.model', 'tire.brand', 'shop', 'opener', 'closer'),
        ]);
    }

    public function send(Request $request, WorkOrder $workOrder, WorkOrderService $service)
    {
        AccessScope::abortUnlessWorkOrder($request->user(), $workOrder->id);
        try {
            $service->sendToShop($workOrder, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'La cubierta quedó en taller.');
    }

    public function close(Request $request, WorkOrder $workOrder, WorkOrderService $service)
    {
        AccessScope::abortUnlessWorkOrder($request->user(), $workOrder->id);
        $data = $request->validate([
            'cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);
        try {
            $service->close($workOrder, $request->user(), isset($data['cost']) ? (float) $data['cost'] : null, $data['notes'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Orden cerrada. El historial de la cubierta quedó asentado.');
    }

    public function cancel(Request $request, WorkOrder $workOrder, WorkOrderService $service)
    {
        AccessScope::abortUnlessWorkOrder($request->user(), $workOrder->id);
        $data = $request->validate(['notes' => 'nullable|string']);
        try {
            $service->cancel($workOrder, $request->user(), $data['notes'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Orden cancelada.');
    }
}
