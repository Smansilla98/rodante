<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\TireBrand;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index()
    {
        return view('catalogs.brands', ['brands' => TireBrand::withCount('models')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:80|unique:tire_brands,name']);
        TireBrand::create($data + ['is_active' => true]);

        return back()->with('success', 'Marca creada.');
    }

    public function update(Request $request, TireBrand $brand)
    {
        $data = $request->validate([
            'name' => 'required|string|max:80|unique:tire_brands,name,'.$brand->id,
        ]);
        $brand->update($data + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Marca actualizada.');
    }

    public function destroy(TireBrand $brand)
    {
        if ($brand->models()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar: tiene modelos. Desactivala o borralos antes.']);
        }
        $brand->delete();

        return back()->with('success', 'Marca eliminada.');
    }
}
