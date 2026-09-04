<?php

namespace App\Http\Controllers\Purchase;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\Base;
use App\Models\Supplier;
use App\Models\TirePurchase;
use App\Models\TireSize;
use App\Services\PurchaseService;
use App\Support\AccessScope;
use App\Support\TireProductCatalog;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $query = TirePurchase::with('supplier', 'base', 'user')->latest();
        AccessScope::purchases($query, $request->user());

        return view('purchases.index', [
            'purchases' => $query->paginate(20),
        ]);
    }

    public function create(Request $request)
    {
        $bases = Base::where('is_active', true)->orderBy('name');
        if (! AccessScope::seesEverything($request->user())) {
            $ids = AccessScope::visibleBaseIds($request->user());
            $bases->whereIn('id', $ids ?: [0]);
        }

        return view('purchases.create', [
            'suppliers' => tap(Supplier::where('is_active', true)->orderBy('name'), fn ($q) => AccessScope::applyCompany($q, $request->user()))->get(),
            'bases' => $bases->get(),
            'sizes' => TireSize::where('is_active', true)->orderBy('code')->get(),
            'catalog' => TireProductCatalog::uiPayload(),
        ]);
    }

    public function store(Request $request, PurchaseService $purchases)
    {
        $data = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'base_id' => 'required|exists:bases,id',
            'purchased_at' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.tire_brand_id' => 'nullable|exists:tire_brands,id',
            'items.*.tire_model_id' => 'nullable|exists:tire_models,id',
            'items.*.tire_size_id' => 'nullable|exists:tire_sizes,id',
            'items.*.quantity' => 'nullable|integer|min:1|max:200',
            'items.*.first_number' => 'nullable|integer|min:1',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'items.*.dot' => 'nullable|string|max:20|regex:/^[A-Za-z0-9\s\-]{8,20}$/',
        ]);
        $data['items'] = collect($data['items'])->filter(fn ($item) => ! empty($item['tire_brand_id']) && ! empty($item['quantity']))->values()->all();
        foreach ($data['items'] as &$item) {
            if (! empty($item['dot'])) {
                $normalized = \App\Models\Tire::normalizeDot($item['dot']);
                if ($normalized === null || ! preg_match('/^[A-Za-z0-9]{8,20}$/', $normalized)) {
                    return back()->withErrors(['items' => 'El DOT debe tener entre 8 y 20 caracteres (letras y números).'])->withInput();
                }
                $item['dot'] = $normalized;
            } else {
                $item['dot'] = null;
            }
        }
        unset($item);
        if ($data['items'] === []) {
            return back()->withErrors(['items' => 'Cargá al menos una línea de compra.'])->withInput();
        }
        if (! AccessScope::seesEverything($request->user()) && ! in_array((int) $data['base_id'], AccessScope::visibleBaseIds($request->user()), true)) {
            abort(404);
        }

        try {
            $purchase = $purchases->create($data, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['items' => $e->getMessage()])->withInput();
        }

        return redirect()->route('purchases.show', $purchase)->with('success', 'Compra creada en borrador.');
    }

    public function show(Request $request, TirePurchase $purchase)
    {
        $this->authorizeVisible('view', $purchase);

        return view('purchases.show', [
            'purchase' => $purchase->load(['supplier', 'base', 'items.brand', 'items.model', 'items.size', 'items.tires']),
            'suppliers' => Supplier::orderBy('name')->get(),
            'bases' => Base::orderBy('name')->get(),
        ]);
    }

    public function confirm(Request $request, TirePurchase $purchase, PurchaseService $purchases)
    {
        $this->authorizeVisible('view', $purchase);
        $this->authorize('confirm', $purchase);

        try {
            $purchases->confirm($purchase, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['confirm' => $e->getMessage()]);
        }

        return back()->with('success', 'Compra confirmada. Los neumáticos ingresaron a stock.');
    }

    public function update(Request $request, TirePurchase $purchase, PurchaseService $purchases)
    {
        $this->authorizeVisible('view', $purchase);
        $this->authorize('update', $purchase);
        $data = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'base_id' => 'required|exists:bases,id',
            'purchased_at' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        try {
            $purchases->updateDraft($purchase, $data);
        } catch (DomainException $e) {
            return back()->withErrors(['purchase' => $e->getMessage()]);
        }

        return redirect()->route('purchases.show', $purchase)->with('success', 'Compra actualizada.');
    }

    public function destroy(Request $request, TirePurchase $purchase, PurchaseService $purchases)
    {
        $this->authorizeVisible('view', $purchase);
        $this->authorize('delete', $purchase);
        $number = $purchase->number;
        try {
            $purchases->discard($purchase);
        } catch (DomainException $e) {
            return back()->withErrors(['purchase' => $e->getMessage()]);
        }

        return redirect()->route('purchases.index')->with('success', 'Se anuló la compra '.$number.'.');
    }
}
