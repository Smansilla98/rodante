<?php

namespace App\Http\Controllers\Purchase;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\Base;
use App\Models\Supplier;
use App\Models\TirePurchase;
use App\Models\TireSize;
use App\Support\TireProductCatalog;
use App\Services\PurchaseService;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function index()
    {
        return view('purchases.index', [
            'purchases' => TirePurchase::with('supplier', 'base', 'user')->latest()->paginate(20),
        ]);
    }

    public function create()
    {
        return view('purchases.create', [
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'bases' => Base::where('is_active', true)->orderBy('name')->get(),
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
        ]);
        $data['items'] = collect($data['items'])->filter(fn ($item) => ! empty($item['tire_brand_id']) && ! empty($item['quantity']))->values()->all();
        if ($data['items'] === []) {
            return back()->withErrors(['items' => 'Cargá al menos una línea de compra.'])->withInput();
        }

        try {
            $purchase = $purchases->create($data, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['items' => $e->getMessage()])->withInput();
        }

        return redirect()->route('purchases.show', $purchase)->with('success', 'Compra creada en borrador.');
    }

    public function show(TirePurchase $purchase)
    {
        return view('purchases.show', [
            'purchase' => $purchase->load(['supplier', 'base', 'items.brand', 'items.model', 'items.size', 'items.tires']),
            'suppliers' => Supplier::orderBy('name')->get(),
            'bases' => Base::orderBy('name')->get(),
        ]);
    }

    public function confirm(TirePurchase $purchase, PurchaseService $purchases, Request $request)
    {
        try {
            $purchases->confirm($purchase, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['confirm' => $e->getMessage()]);
        }

        return back()->with('success', 'Compra confirmada. Los neumáticos ingresaron a stock.');
    }

    public function update(Request $request, TirePurchase $purchase, PurchaseService $purchases)
    {
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

    public function destroy(TirePurchase $purchase, PurchaseService $purchases)
    {
        $number = $purchase->number;
        try {
            $purchases->discard($purchase);
        } catch (DomainException $e) {
            return back()->withErrors(['purchase' => $e->getMessage()]);
        }

        return redirect()->route('purchases.index')->with('success', 'Se anuló la compra '.$number.'.');
    }
}
