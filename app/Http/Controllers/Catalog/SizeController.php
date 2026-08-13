<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
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
        $data = $request->validate([
            'code' => 'required|string|max:40|unique:tire_sizes,code',
            'alias' => 'nullable|string|max:40',
            'uneven_wear_threshold_mm' => 'nullable|integer|min:1|max:20',
        ]);
        $size = TireSize::create($data + ['is_active' => true, 'uneven_wear_threshold_mm' => $data['uneven_wear_threshold_mm'] ?? 3]);
        $this->seedZones($size);

        return back()->with('success', 'Medida creada.');
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
