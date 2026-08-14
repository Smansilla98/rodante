<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Base;
use App\Models\Fleet;
use App\Models\FleetUnit;
use App\Models\MovementReason;
use App\Models\Supplier;
use App\Models\TireMovement;
use App\Models\UnitConfiguration;
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
        $data = $this->fleetData($request);
        $fleet = Fleet::create($data + ['is_active' => true]);
        $fleet->bases()->sync($data['base_ids'] ?? []);

        return back()->with('success', 'Flota creada.');
    }

    public function updateFleet(Request $request, Fleet $fleet)
    {
        $data = $this->fleetData($request, $fleet);
        $fleet->update($data + ['is_active' => $request->boolean('is_active')]);
        $fleet->bases()->sync($data['base_ids'] ?? []);

        return back()->with('success', 'Flota actualizada.');
    }

    public function destroyFleet(Fleet $fleet)
    {
        if ($fleet->units()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar: hay unidades en esta flota. Desactivala.']);
        }
        $fleet->bases()->detach();
        $fleet->delete();

        return back()->with('success', 'Flota eliminada.');
    }

    public function bases()
    {
        return view('catalogs.bases', ['bases' => Base::orderBy('name')->get()]);
    }

    public function storeBase(Request $request)
    {
        $data = $this->baseData($request);
        Base::create($data + ['is_active' => true]);

        return back()->with('success', 'Base creada.');
    }

    public function updateBase(Request $request, Base $base)
    {
        $data = $this->baseData($request, $base);
        $base->update($data + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Base actualizada.');
    }

    public function destroyBase(Base $base)
    {
        if ($base->units()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar: hay unidades en esta base. Desactivala.']);
        }
        $base->fleets()->detach();
        $base->delete();

        return back()->with('success', 'Base eliminada.');
    }

    public function suppliers()
    {
        return view('catalogs.suppliers', ['suppliers' => Supplier::orderBy('name')->get()]);
    }

    public function storeSupplier(Request $request)
    {
        $data = $this->supplierData($request);
        Supplier::create($data + ['is_active' => true]);

        return back()->with('success', 'Proveedor creado.');
    }

    public function updateSupplier(Request $request, Supplier $supplier)
    {
        $data = $this->supplierData($request);
        $supplier->update($data + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Proveedor actualizado.');
    }

    public function destroySupplier(Supplier $supplier)
    {
        if ($supplier->purchases()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar: tiene compras. Desactivalo.']);
        }
        $supplier->delete();

        return back()->with('success', 'Proveedor eliminado.');
    }

    public function types()
    {
        return view('catalogs.types', [
            'types' => UnitType::orderBy('id')->get(),
            'configurations' => UnitConfiguration::with('positions')->orderBy('id')->get(),
            'reasons' => MovementReason::orderBy('applies_to')->orderBy('name')->get(),
        ]);
    }

    public function storeType(Request $request)
    {
        $data = $this->typeData($request);
        UnitType::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'has_odometer' => $request->boolean('has_odometer'),
            'is_active' => true,
        ]);

        return back()->with('success', 'Tipo de unidad creado.');
    }

    public function updateType(Request $request, UnitType $type)
    {
        $data = $this->typeData($request, $type);
        $type->update([
            'code' => $data['code'],
            'name' => $data['name'],
            'has_odometer' => $request->boolean('has_odometer'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Tipo actualizado.');
    }

    public function destroyType(UnitType $type)
    {
        if ($type->units()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar: hay unidades de este tipo. Desactivalo.']);
        }
        $type->delete();

        return back()->with('success', 'Tipo eliminado.');
    }

    public function storeReason(Request $request)
    {
        $data = $this->reasonData($request);
        MovementReason::create($data + ['is_active' => true]);

        return back()->with('success', 'Motivo creado.');
    }

    public function updateReason(Request $request, MovementReason $reason)
    {
        $data = $this->reasonData($request, $reason);
        $reason->update($data + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Motivo actualizado.');
    }

    public function destroyReason(MovementReason $reason)
    {
        if (TireMovement::where('reason_id', $reason->id)->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar: ya se usó en movimientos. Desactivalo.']);
        }
        $reason->delete();

        return back()->with('success', 'Motivo eliminado.');
    }

    public function updateConfiguration(Request $request, UnitConfiguration $configuration)
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'description' => 'nullable|string|max:255',
        ]);
        $configuration->update($data + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Configuración actualizada.');
    }

    public function destroyConfiguration(UnitConfiguration $configuration)
    {
        if (FleetUnit::where('unit_configuration_id', $configuration->id)->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar: hay unidades con esta configuración. Desactivala.']);
        }
        $configuration->positions()->delete();
        $configuration->delete();

        return back()->with('success', 'Configuración eliminada.');
    }

    private function fleetData(Request $request, ?Fleet $fleet = null): array
    {
        $unique = 'unique:fleets,code'.($fleet ? ','.$fleet->id : '');

        return $request->validate([
            'name' => 'required|string|max:80',
            'code' => 'required|string|max:20|'.$unique,
            'base_ids' => 'array',
            'base_ids.*' => 'exists:bases,id',
        ]);
    }

    private function baseData(Request $request, ?Base $base = null): array
    {
        $unique = 'unique:bases,code'.($base ? ','.$base->id : '');

        return $request->validate([
            'name' => 'required|string|max:80',
            'code' => 'required|string|max:20|'.$unique,
            'location' => 'nullable|string|max:120',
        ]);
    }

    private function supplierData(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'tax_id' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:30',
        ]);
    }

    private function typeData(Request $request, ?UnitType $type = null): array
    {
        $unique = 'unique:unit_types,code'.($type ? ','.$type->id : '');

        return $request->validate([
            'code' => 'required|string|max:30|'.$unique,
            'name' => 'required|string|max:80',
        ]);
    }

    private function reasonData(Request $request, ?MovementReason $reason = null): array
    {
        $unique = 'unique:movement_reasons,code'.($reason ? ','.$reason->id : '');

        return $request->validate([
            'code' => 'required|string|max:40|'.$unique,
            'name' => 'required|string|max:80',
            'applies_to' => 'required|in:RETIRO,BAJA,OTRO',
        ]);
    }
}
