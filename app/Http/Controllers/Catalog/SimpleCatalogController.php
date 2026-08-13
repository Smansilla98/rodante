<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Base;
use App\Models\Fleet;
use App\Models\MovementReason;
use App\Models\Supplier;
use App\Models\UnitType;
use Illuminate\Http\Request;

class SimpleCatalogController extends Controller
{
    public function fleets()
    {
        return view('catalogs.fleets', [
            'fleets' => Fleet::with('bases')->orderBy('name')->get(),
            'bases' => Base::orderBy('name')->get(),
        ]);
    }

    public function storeFleet(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'code' => 'required|string|max:20|unique:fleets,code',
            'base_ids' => 'array',
        ]);
        $fleet = Fleet::create($data + ['is_active' => true]);
        $fleet->bases()->sync($data['base_ids'] ?? []);

        return back()->with('success', 'Flota creada.');
    }

    public function bases()
    {
        return view('catalogs.bases', ['bases' => Base::orderBy('name')->get()]);
    }

    public function storeBase(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'code' => 'required|string|max:20|unique:bases,code',
            'location' => 'nullable|string|max:120',
        ]);
        Base::create($data + ['is_active' => true]);

        return back()->with('success', 'Base creada.');
    }

    public function suppliers()
    {
        return view('catalogs.suppliers', ['suppliers' => Supplier::orderBy('name')->get()]);
    }

    public function storeSupplier(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'tax_id' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:30',
        ]);
        Supplier::create($data + ['is_active' => true]);

        return back()->with('success', 'Proveedor creado.');
    }

    public function types()
    {
        return view('catalogs.types', [
            'types' => UnitType::orderBy('name')->get(),
            'reasons' => MovementReason::orderBy('applies_to')->orderBy('name')->get(),
        ]);
    }

    public function storeType(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:30|unique:unit_types,code',
            'name' => 'required|string|max:80',
            'has_odometer' => 'sometimes|boolean',
        ]);
        UnitType::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'has_odometer' => $request->boolean('has_odometer'),
            'is_active' => true,
        ]);

        return back()->with('success', 'Tipo de unidad creado.');
    }

    public function storeReason(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:40|unique:movement_reasons,code',
            'name' => 'required|string|max:80',
            'applies_to' => 'required|in:RETIRO,BAJA,OTRO',
        ]);
        MovementReason::create($data + ['is_active' => true]);

        return back()->with('success', 'Motivo creado.');
    }
}
