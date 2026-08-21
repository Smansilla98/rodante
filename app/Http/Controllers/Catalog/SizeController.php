<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Tire;
use App\Models\TireSize;
use Illuminate\Http\Request;

class SizeController extends Controller
{
    public function index()
    {
        return view('catalogs.sizes', ['sizes' => TireSize::with('zones')->orderBy('code')->get()]);
    }

    public function store(Request $request)
    {
        $this->authorize('manageCatalogs');
        $data = $this->validated($request);
        $size = TireSize::create($this->payload($data) + ['is_active' => true]);
        $this->seedZones($size);

        return back()->with('success', 'Medida creada.');
    }

    public function update(Request $request, TireSize $size)
    {
        $this->authorize('manageCatalogs');
        $data = $this->validated($request, $size);
        $size->update($this->payload($data) + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Medida actualizada.');
    }

    public function destroy(TireSize $size)
    {
        $this->authorize('manageCatalogs');
        if (Tire::where('tire_size_id', $size->id)->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar: hay cubiertas con esa medida. Desactivala.']);
        }
        $size->zones()->delete();
        $size->models()->detach();
        $size->delete();

        return back()->with('success', 'Medida eliminada.');
    }

    private function validated(Request $request, ?TireSize $size = null): array
    {
        $unique = 'unique:tire_sizes,code'.($size ? ','.$size->id : '');

        return $request->validate([
            'code' => 'required|string|max:40|'.$unique,
            'alias' => 'nullable|string|max:40',
            'uneven_wear_threshold_mm' => 'nullable|integer|min:1|max:20',
        ]);
    }

    private function payload(array $data): array
    {
        $parsed = $this->parseCode($data['code']);

        return $data + $parsed + [
            'uneven_wear_threshold_mm' => $data['uneven_wear_threshold_mm'] ?? 3,
        ];
    }

    private function parseCode(string $code): array
    {
        if (! preg_match('/(\d+)\s*\/\s*(\d+)\s*R\s*([\d.]+)/i', $code, $match)) {
            return [];
        }

        return [
            'width_mm' => (int) $match[1],
            'aspect_ratio' => (int) $match[2],
            'rim_inches' => $match[3],
        ];
    }

    private function seedZones(TireSize $size): void
    {
        foreach ([
            ['FLANCO_IZQ', 'Flanco izquierdo', 1],
            ['FLANCO_DER', 'Flanco derecho', 2],
            ['CENTRAL', 'Central', 3],
            ['PERIMETRAL', 'Alrededor', 4],
        ] as [$code, $name, $order]) {
            $size->zones()->create(['code' => $code, 'name' => $name, 'sort_order' => $order]);
        }
    }
}
