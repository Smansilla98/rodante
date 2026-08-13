<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\TireBrand;
use App\Models\TireModel;
use App\Models\TireSize;
use Illuminate\Http\Request;

class ModelController extends Controller
{
    public function index()
    {
        return view('catalogs.models', [
            'models' => TireModel::with('brand', 'sizes')->orderBy('code')->get(),
            'brands' => TireBrand::where('is_active', true)->orderBy('name')->get(),
            'sizes' => TireSize::where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tire_brand_id' => 'required|exists:tire_brands,id',
            'code' => 'required|string|max:40',
            'name' => 'nullable|string|max:80',
            'size_ids' => 'array',
            'size_ids.*' => 'exists:tire_sizes,id',
        ]);
        $model = TireModel::create($data);
        $model->sizes()->sync($data['size_ids'] ?? []);

        return back()->with('success', 'Modelo creado.');
    }

    public function update(Request $request, TireModel $model)
    {
        $data = $request->validate([
            'tire_brand_id' => 'required|exists:tire_brands,id',
            'code' => 'required|string|max:40',
            'name' => 'nullable|string|max:80',
            'is_active' => 'sometimes|boolean',
            'size_ids' => 'array',
            'size_ids.*' => 'exists:tire_sizes,id',
        ]);
        $model->update($data);
        $model->sizes()->sync($data['size_ids'] ?? []);

        return back()->with('success', 'Modelo actualizado.');
    }
}
