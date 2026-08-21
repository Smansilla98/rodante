<?php

namespace App\Http\Controllers\Catalog;

use App\Enums\TireApplication;
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
            'models' => TireModel::with('brand', 'sizes')->get()
                ->sortBy(fn (TireModel $model) => $model->brand->name.' '.$model->code)
                ->values(),
            'brands' => TireBrand::orderBy('name')->get(),
            'sizes' => TireSize::orderBy('code')->get(),
            'applications' => TireApplication::cases(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('manageCatalogs');
        $data = $this->validated($request);
        $model = TireModel::create($data + ['is_active' => true]);
        $model->sizes()->sync($data['size_ids'] ?? []);

        return back()->with('success', 'Modelo creado.');
    }

    public function update(Request $request, TireModel $model)
    {
        $this->authorize('manageCatalogs');
        $data = $this->validated($request, $model);
        $model->update($data + ['is_active' => $request->boolean('is_active')]);
        $model->sizes()->sync($data['size_ids'] ?? []);

        return back()->with('success', 'Modelo actualizado.');
    }

    public function destroy(TireModel $model)
    {
        $this->authorize('manageCatalogs');
        if ($model->tires()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar: hay cubiertas de este modelo. Desactivalo.']);
        }
        $model->sizes()->detach();
        $model->delete();

        return back()->with('success', 'Modelo eliminado.');
    }

    private function validated(Request $request, ?TireModel $model = null): array
    {
        return $request->validate([
            'tire_brand_id' => 'required|exists:tire_brands,id',
            'code' => 'required|string|max:40',
            'name' => 'nullable|string|max:80',
            'application' => 'required|in:DIRECCION,TRACCION,ARRASTRE,MIXTO',
            'size_ids' => 'array',
            'size_ids.*' => 'exists:tire_sizes,id',
        ]);
    }
}
