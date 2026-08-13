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
            'is_active' => 'sometimes|boolean',
        ]);
        $brand->update($data);

        return back()->with('success', 'Marca actualizada.');
    }
}
