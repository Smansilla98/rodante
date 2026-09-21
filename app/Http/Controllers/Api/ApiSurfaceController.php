<?php

namespace App\Http\Controllers\Api;

use App\Enums\WorkOrderType;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkOrderRequest;
use App\Models\Base;
use App\Models\InventorySession;
use App\Models\MovementReason;
use App\Models\RetreadShop;
use App\Models\Supplier;
use App\Models\Tire;
use App\Models\TireBrand;
use App\Models\TireModel;
use App\Models\TireSize;
use App\Models\WorkOrder;
use App\Services\InventoryService;
use App\Services\PurchaseService;
use App\Services\WorkOrderService;
use App\Support\AccessScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiSurfaceController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->load('company', 'fleets', 'bases'));
    }

    /**
     * Resumen del inicio en UNA sola respuesta (antes el dashboard mobile hacía un
     * GET /tires por cada KPI, en paralelo — lento y frágil: si uno fallaba, Promise.all
     * tiraba abajo todo el resumen). Un solo query agrupado por status, más 2 contadores.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        $tireQuery = Tire::query();
        AccessScope::tires($tireQuery, $user);
        $byStatus = (clone $tireQuery)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $workOrderQuery = WorkOrder::query();
        AccessScope::workOrders($workOrderQuery, $user);
        $openWorkOrders = (clone $workOrderQuery)->whereIn('status', ['ABIERTA', 'EN_TALLER'])->count();

        $inventoryQuery = InventorySession::query();
        AccessScope::inventorySessions($inventoryQuery, $user);
        $openInventorySessions = (clone $inventoryQuery)->whereNotIn('status', ['CLOSED', 'CANCELLED'])->count();

        return response()->json([
            'tires_total' => (int) $byStatus->sum(),
            'tires_by_status' => $byStatus,
            'open_work_orders' => $openWorkOrders,
            'open_inventory_sessions' => $openInventorySessions,
        ]);
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

    /** Catálogo de motivos de movimiento (retiro/baja), para reemplazar los reason_id "a mano" en los clientes. */
    public function movementReasons(Request $request): JsonResponse
    {
        $query = MovementReason::query()->where('is_active', true)->orderBy('name');
        AccessScope::applyCompany($query, $request->user());
        if ($request->filled('applies_to')) {
            $query->where('applies_to', $request->string('applies_to'));
        }

        return response()->json($query->get(['id', 'code', 'name', 'applies_to']));
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

    /** Talleres de recapado, para elegir por nombre en vez de mandar un retread_shop_id a mano. */
    public function retreadShops(Request $request): JsonResponse
    {
        $query = RetreadShop::query()->where('is_active', true)->orderBy('name');
        AccessScope::applyCompany($query, $request->user());

        return response()->json($query->get(['id', 'name']));
    }

    public function showWorkOrder(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorizeVisible('view', $workOrder);

        return response()->json(
            $workOrder->load('tire.model', 'tire.brand', 'items.tire.model', 'items.tire.brand', 'shop', 'opener', 'closer')
        );
    }

    public function sendWorkOrderToShop(Request $request, WorkOrder $workOrder, WorkOrderService $service): JsonResponse
    {
        $this->authorizeVisible('view', $workOrder);
        $this->authorize('manage', $workOrder);
        try {
            $service->sendToShop($workOrder, $request->user());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($workOrder->fresh(['tire', 'items.tire', 'shop']));
    }

    public function closeWorkOrder(Request $request, WorkOrder $workOrder, WorkOrderService $service): JsonResponse
    {
        $this->authorizeVisible('view', $workOrder);
        $this->authorize('manage', $workOrder);
        $data = $request->validate([
            'cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);
        try {
            $service->close($workOrder, $request->user(), isset($data['cost']) ? (float) $data['cost'] : null, $data['notes'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($workOrder->fresh(['tire', 'items.tire', 'shop']));
    }

    public function cancelWorkOrder(Request $request, WorkOrder $workOrder, WorkOrderService $service): JsonResponse
    {
        $this->authorizeVisible('view', $workOrder);
        $this->authorize('manage', $workOrder);
        $data = $request->validate(['notes' => 'nullable|string']);
        try {
            $service->cancel($workOrder, $request->user(), $data['notes'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($workOrder->fresh(['tire', 'items.tire', 'shop']));
    }

    /** Catálogos para el formulario de alta: marcas, modelos (con sus medidas compatibles), medidas y proveedores. */
    public function tireCatalog(Request $request): JsonResponse
    {
        return response()->json([
            'brands' => TireBrand::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'models' => TireModel::where('is_active', true)->with('sizes:id')->orderBy('code')->get()
                ->map(fn (TireModel $m) => [
                    'id' => $m->id,
                    'code' => $m->code,
                    'name' => $m->name,
                    'tire_brand_id' => $m->tire_brand_id,
                    'application' => $m->application?->value,
                    'application_label' => $m->application?->label(),
                    'size_ids' => $m->sizes->pluck('id'),
                ])
                ->values(),
            'sizes' => TireSize::orderBy('code')->get(['id', 'code', 'alias']),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Alta de UNA cubierta — reusa PurchaseService::create()+confirm() tal cual (crea una
     * "compra" de un solo ítem y la confirma en el momento), no duplica su lógica de
     * numeración/lifecycle/movimientos. Dos casos, sin un flag separado para elegirlos:
     *   - Compra real: mandá `unit_cost` > 0 → PurchaseService ya registra el costo.
     *   - Puesta a punto (cubierta que ya estaba comprada, carga manual): omitilo o
     *     mandá 0 → PurchaseService ya se salta el registro de costo cuando es <= 0.
     */
    public function storeTire(Request $request, PurchaseService $purchases): JsonResponse
    {
        abort_unless($request->user()->role->canWrite(), 403, 'No tiene permiso para cargar cubiertas.');

        $data = $request->validate([
            'tire_brand_id' => 'required|exists:tire_brands,id',
            'tire_model_id' => 'required|exists:tire_models,id',
            'tire_size_id' => 'required|exists:tire_sizes,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'base_id' => 'required|exists:bases,id',
            'dot' => 'nullable|string|max:20',
            'purchased_at' => 'nullable|date',
            'unit_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        try {
            $purchase = $purchases->create([
                'supplier_id' => $data['supplier_id'],
                'base_id' => $data['base_id'],
                'purchased_at' => $data['purchased_at'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
                'items' => [[
                    'tire_brand_id' => $data['tire_brand_id'],
                    'tire_model_id' => $data['tire_model_id'],
                    'tire_size_id' => $data['tire_size_id'],
                    'quantity' => 1,
                    'dot' => $data['dot'] ?? null,
                    'unit_cost' => $data['unit_cost'] ?? null,
                ]],
            ], $request->user());

            $confirmed = $purchases->confirm($purchase, $request->user())->load('items.tires.brand', 'items.tires.model', 'items.tires.size');
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $tire = $confirmed->items->first()?->tires->first();

        return response()->json($tire, 201);
    }

    public function inventorySessions(Request $request): JsonResponse
    {
        $query = InventorySession::with('base')->latest('opened_at');
        AccessScope::inventorySessions($query, $request->user());

        return response()->json($query->paginate(50));
    }

    public function storeInventorySession(Request $request, InventoryService $inventories): JsonResponse
    {
        $this->authorize('create', InventorySession::class);
        $data = $request->validate([
            'base_id' => 'required|exists:bases,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $session = $inventories->open($request->user(), Base::findOrFail($data['base_id']), $data['notes'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($session->load('base'), 201);
    }

    public function showInventorySession(Request $request, InventorySession $inventory): JsonResponse
    {
        $this->authorizeVisible('view', $inventory);
        $inventory->load(['base', 'opener', 'closer', 'approver']);
        $lines = $inventory->lines()
            ->with(['tire.brand', 'tire.model', 'expectedBase', 'observedBase', 'scanner'])
            ->orderByRaw("CASE delta
                WHEN 'MISSING' THEN 1
                WHEN 'WRONG_BASE' THEN 2
                WHEN 'UNEXPECTED' THEN 3
                WHEN 'MOUNTED' THEN 4
                WHEN 'OK' THEN 5
                ELSE 6 END")
            ->orderBy('id')
            ->paginate(80);

        return response()->json(['session' => $inventory, 'lines' => $lines]);
    }

    public function startInventorySession(Request $request, InventorySession $inventory, InventoryService $inventories): JsonResponse
    {
        $this->authorize('count', $inventory);
        try {
            $inventories->startCounting($inventory, $request->user());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($inventory->fresh(['base']));
    }

    public function scanInventorySession(Request $request, InventorySession $inventory, InventoryService $inventories): JsonResponse
    {
        $this->authorize('count', $inventory);
        $data = $request->validate(['q' => 'required|string|max:80']);

        try {
            $line = $inventories->scan($inventory, $request->user(), $data['q']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($line->load('tire.brand', 'tire.model'));
    }

    public function reviewInventorySession(Request $request, InventorySession $inventory, InventoryService $inventories): JsonResponse
    {
        $this->authorize('count', $inventory);
        try {
            $inventories->submitForReview($inventory, $request->user());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($inventory->fresh(['base']));
    }

    public function closeInventorySession(Request $request, InventorySession $inventory, InventoryService $inventories): JsonResponse
    {
        $this->authorize('close', $inventory);
        $data = $request->validate([
            'apply_fixes' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);
        $apply = (bool) ($data['apply_fixes'] ?? false);
        if ($apply) {
            $this->authorize('adjust', $inventory);
        }

        try {
            $inventories->close($inventory, $request->user(), $apply, $data['notes'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($inventory->fresh(['base']));
    }

    public function cancelInventorySession(Request $request, InventorySession $inventory, InventoryService $inventories): JsonResponse
    {
        $this->authorize('cancel', $inventory);
        $data = $request->validate(['notes' => 'nullable|string|max:1000']);
        try {
            $inventories->cancel($inventory, $request->user(), $data['notes'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($inventory->fresh(['base']));
    }

    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => 'required|string|max:100']);
        $term = trim($data['q']);
        $query = Tire::with('brand', 'model', 'size', 'currentLocation.unit', 'currentLocation.base', 'currentLocation.position');
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
