@extends('layouts.app')
@section('kicker', 'Operación')
@section('title', 'Editar '.$unit->plate)
@section('content')
<x-page-header kicker="Operación" title="Editar {{ $unit->plate }}" subtitle="Datos de la unidad. El tipo y la configuración de ejes se cambian desde la planilla.">
    <x-slot:actions>
        <a href="{{ route('units.show', $unit) }}" class="btn btn-ghost"><x-icon name="back" class="w-4 h-4" /> Planilla</a>
    </x-slot:actions>
</x-page-header>

<form method="POST" action="{{ route('units.update', $unit) }}" class="panel max-w-xl" id="unit-form">
    @csrf
    @method('PUT')
    <div class="panel__body space-y-4">
        <label class="field"><span>Patente</span><input name="plate" value="{{ old('plate', $unit->plate) }}" required></label>
        <label class="field"><span>Flota</span>
            <select name="fleet_id" required>@foreach($fleets as $fleet)<option value="{{ $fleet->id }}" @selected((string) old('fleet_id', $unit->fleet_id) === (string) $fleet->id)>{{ $fleet->name }}</option>@endforeach</select>
        </label>
        <label class="field"><span>Base</span>
            <select name="base_id" required>@foreach($bases as $base)<option value="{{ $base->id }}" @selected((string) old('base_id', $unit->base_id) === (string) $base->id)>{{ $base->name }}</option>@endforeach</select>
        </label>
        <p class="hint">{{ $unit->type->name }} · {{ $unit->configuration->label() }}</p>
        <label class="field"><span>Estado</span>
            <select name="status" required>
                @foreach(\App\Enums\UnitStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(old('status', $unit->status->value) === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="field"><span>Marca del equipo</span><input name="brand" value="{{ old('brand', $unit->brand) }}" placeholder="Scania, Volvo, Bonano…"></label>
        <label class="field"><span>Modelo</span><input name="model_name" value="{{ old('model_name', $unit->model_name) }}"></label>
        <label class="field"><span>Notas</span><textarea name="notes" rows="2">{{ old('notes', $unit->notes) }}</textarea></label>

        @unless($unit->hasOdometer())
            @php $specs = old('specs', $unit->specs ?? []); @endphp
            <fieldset class="space-y-4" id="trailerSpecs">
                <legend class="text-sm font-semibold">Datos del chasis / tanque</legend>
                <label class="field"><span>Capacidad (L)</span><input name="specs[capacity_l]" type="number" min="0" value="{{ $specs['capacity_l'] ?? '' }}"></label>
                <label class="field"><span>Compartimentos</span><input name="specs[compartments]" type="number" min="0" max="20" value="{{ $specs['compartments'] ?? '' }}"></label>
                <label class="field"><span>Material</span><input name="specs[material]" value="{{ $specs['material'] ?? '' }}" placeholder="Acero, aluminio…"></label>
                <label class="field"><span>Producto</span><input name="specs[product]" value="{{ $specs['product'] ?? '' }}" placeholder="Combustible, alcohol…"></label>
                <label class="field"><span>Suspensión</span>
                    <select name="specs[suspension]">
                        <option value="">Sin especificar</option>
                        <option value="neumatica" @selected(($specs['suspension'] ?? '') === 'neumatica')>Neumática</option>
                        <option value="mecanica" @selected(($specs['suspension'] ?? '') === 'mecanica')>Mecánica</option>
                        <option value="mixta" @selected(($specs['suspension'] ?? '') === 'mixta')>Mixta</option>
                    </select>
                </label>
                <label class="field"><span>Cubiertas lineales</span>
                    <select name="specs[tire_width]" required>
                        <option value="">Elegir medida</option>
                        <option value="295" @selected((string) ($specs['tire_width'] ?? '') === '295')>295 — lineal</option>
                        <option value="385" @selected((string) ($specs['tire_width'] ?? '') === '385')>385 — lineal (gomón)</option>
                    </select>
                </label>
            </fieldset>
        @endunless
        <button class="btn btn-primary">Guardar cambios</button>
    </div>
</form>

<div class="max-w-xl mt-4">
    <x-abm-delete :action="route('units.destroy', $unit)" confirm="¿Dar de baja o eliminar esta unidad?" label="Eliminar / dar de baja" />
</div>
@endsection
