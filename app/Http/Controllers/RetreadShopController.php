<?php

namespace App\Http\Controllers;

use App\Models\RetreadShop;
use App\Support\AccessScope;
use Illuminate\Http\Request;

class RetreadShopController extends Controller
{
    public function index(Request $request)
    {
        $query = RetreadShop::query()->orderBy('name');
        AccessScope::applyCompany($query, $request->user());
        $editing = $query->get()->firstWhere('id', (int) $request->query('edit'));

        return view('shops.index', [
            'shops' => $query->get(),
            'editing' => $editing,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'tax_id' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:40',
            'address' => 'nullable|string|max:160',
        ]);
        RetreadShop::create($data + ['company_id' => $request->user()->company_id, 'is_active' => true]);

        return back()->with('success', 'Recapadora cargada.');
    }

    public function update(Request $request, RetreadShop $shop)
    {
        abort_unless((int) $shop->company_id === (int) $request->user()->company_id, 404);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'tax_id' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:40',
            'address' => 'nullable|string|max:160',
        ]);
        $shop->update($data + ['is_active' => $request->boolean('is_active', true)]);

        return redirect()->route('shops.index')->with('success', 'Recapadora actualizada.');
    }
}
