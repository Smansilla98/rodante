<?php

namespace App\Http\Controllers\Purchase;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\Base;
use App\Models\Supplier;
use App\Models\TireBrand;
use App\Models\TireModel;
use App\Models\TirePurchase;
use App\Models\TireSize;
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
            'brands' => TireBrand::where('is_active', true)->with(['models' => fn ($q) => $q->where('is_active', true)->with('sizes')])->orderBy('name')->get(),
            'sizes' => TireSize::where('is_active', true)->orderBy('code')->get(),
            'models' => TireModel::where('is_active', true)->with('brand', 'sizes')->orderBy('code')->get(),
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

        $purchase = $purchases->create($data, $request->user());

        return redirect()->route('purchases.show', $purchase)->with('success', 'Compra creada en borrador.');
    }

    public function show(TirePurchase $purchase)
    {
        return view('purchases.show', [
            'purchase' => $purchase->load(['supplier', 'base', 'items.brand', 'items.model', 'items.size', 'items.tires']),
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
}
