<?php

namespace App\Http\Controllers\Api;

use App\Enums\WorkOrderType;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkOrderRequest;
use App\Models\Base;
use App\Models\InventorySession;
use App\Models\RetreadShop;
use App\Models\Tire;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use App\Support\AccessScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiSurfaceController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->load('company', 'fleets', 'bases'));
    }

    public function bases(Request $request): JsonResponse
    {
        $query = Base::query()->orderBy('name');
        AccessScope::applyCompany($query, $request->user());
        if (! AccessScope::seesEverything($request->user())) {
            $query->whereIn('id', AccessScope::visibleBaseIds($request->user()));
        }

        return response()->json($query->get());
    }

    public function workOrders(Request $request): JsonResponse
    {
        $query = WorkOrder::with('tire.brand', 'tire.model', 'items.tire.model', 'items.tire.brand', 'shop')->latest();
        AccessScope::workOrders($query, $request->user());

        return response()->json($query->paginate(50));
    }

    public function storeWorkOrder(StoreWorkOrderRequest $request, WorkOrderService $service): JsonResponse
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
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($order->load('tire', 'items.tire', 'shop'), 201);
    }

    public function inventorySessions(Request $request): JsonResponse
    {
        $query = InventorySession::with('base')->latest('opened_at');
        AccessScope::inventorySessions($query, $request->user());

        return response()->json($query->paginate(50));
    }

    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => 'required|string|max:100']);
        $term = trim($data['q']);
        $query = Tire::with('brand', 'model', 'size', 'currentLocation.unit', 'currentLocation.base');
        AccessScope::tires($query, $request->user());
        $query->where(function ($inner) use ($term) {
            if (ctype_digit($term)) {
                $inner->where('individual_number', (int) $term);
            }
            $inner->orWhere('public_token', $term);
        });

        $tire = $query->first();

        return $tire
            ? response()->json($tire)
            : response()->json(['message' => 'Neumático no encontrado.'], 404);
    }
}
