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
use App\Services\PurchaseService;
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
