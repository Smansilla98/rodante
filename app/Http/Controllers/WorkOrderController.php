<?php

namespace App\Http\Controllers;

use App\Enums\WorkOrderType;
use App\Exceptions\DomainException;
use App\Http\Requests\CloseWorkOrderRequest;
use App\Http\Requests\StoreWorkOrderRequest;
use App\Models\RetreadShop;
use App\Models\Tire;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use App\Support\AccessScope;
use Illuminate\Http\Request;

class WorkOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = WorkOrder::with('tire.model', 'tire.brand', 'items.tire.model', 'items.tire.brand', 'shop')->latest();
        AccessScope::workOrders($query, $request->user());

        return view('work-orders.index', [
            'orders' => $query->paginate(30),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', WorkOrder::class);
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

    public function store(StoreWorkOrderRequest $request, WorkOrderService $service)
    {
        $data = $request->validated();

        try {
            $order = $service->open(
                $request->user(),
                Tire::query()->whereIn('id', $request->tireIds())->get(),
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
        $this->authorizeVisible('view', $workOrder);

        return view('work-orders.show', [
            'order' => $workOrder->load('tire.model', 'tire.brand', 'items.tire.model', 'items.tire.brand', 'shop', 'opener', 'closer'),
        ]);
    }

    public function send(Request $request, WorkOrder $workOrder, WorkOrderService $service)
    {
        $this->authorizeVisible('view', $workOrder);
        $this->authorize('manage', $workOrder);
        try {
            $service->sendToShop($workOrder, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Las cubiertas quedaron en taller.');
    }

    public function close(CloseWorkOrderRequest $request, WorkOrder $workOrder, WorkOrderService $service)
    {
        $data = $request->validated();
        try {
            $service->close($workOrder, $request->user(), isset($data['cost']) ? (float) $data['cost'] : null, $data['notes'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Orden cerrada. El historial de la cubierta quedó asentado.');
    }

    public function cancel(Request $request, WorkOrder $workOrder, WorkOrderService $service)
    {
        $this->authorizeVisible('view', $workOrder);
        $this->authorize('manage', $workOrder);
        $data = $request->validate(['notes' => 'nullable|string']);
        try {
            $service->cancel($workOrder, $request->user(), $data['notes'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Orden cancelada.');
    }
}
