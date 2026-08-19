@extends('layouts.app')
@section('kicker', 'Operación')
@section('title', 'Nueva unidad')
@section('content')
<x-page-header kicker="Operación" title="Nueva unidad" subtitle="El tipo de unidad va separado de la configuración de ejes y cubiertas.">
    <x-slot:actions>
        <a href="{{ route('units.index') }}" class="btn btn-ghost"><x-icon name="back" class="w-4 h-4" /> Volver</a>
    </x-slot:actions>
</x-page-header>

<form method="POST" action="{{ route('units.store') }}" class="panel max-w-xl" id="unit-form">
    @csrf
    <div class="panel__body space-y-4">
        <label class="field"><span>Patente</span><input name="plate" class="inp" value="{{ old('plate') }}" required @error('plate') aria-invalid="true" @enderror><x-field-error name="plate" /></label>
        <label class="field"><span>Flota</span>
            <select name="fleet_id" class="inp" required>@foreach($fleets as $fleet)<option value="{{ $fleet->id }}" @selected(old('fleet_id')==$fleet->id)>{{ $fleet->name }}</option>@endforeach</select>
            <x-field-error name="fleet_id" />
        </label>
        <label class="field"><span>Base</span>
            <select name="base_id" class="inp" required>@foreach($bases as $base)<option value="{{ $base->id }}" @selected(old('base_id')==$base->id)>{{ $base->name }}</option>@endforeach</select>
            <x-field-error name="base_id" />
        </label>
        <label class="field"><span>Tipo</span>
            <select name="unit_type_id" id="unitType" required>
                @foreach($types as $type)
                    <option value="{{ $type->id }}" data-code="{{ $type->code }}" data-powered="{{ $type->has_odometer ? '1' : '0' }}" @selected(old('unit_type_id')==$type->id)>{{ $type->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="field"><span>Configuración de ejes</span>
            <select name="unit_configuration_id" id="unitConfig" required>
                @foreach($configurations as $cfg)
                    <option
                        value="{{ $cfg->id }}"
                        data-types="{{ implode(',', $cfg->compatible_types ?? []) }}"
                        title="{{ $cfg->description }}"
                        @selected(old('unit_configuration_id')==$cfg->id)
                    >{{ $cfg->label() }}</option>
                @endforeach
            </select>
            <span class="hint" id="configHint"></span>
        </label>
        <label class="field" id="odometerField"><span>Odómetro actual</span><input name="current_odometer" type="number" min="0" value="{{ old('current_odometer') }}" placeholder="Solo tractor / camión"></label>
        <label class="field"><span>Marca del equipo</span><input name="brand" value="{{ old('brand') }}" placeholder="Scania, Volvo, Bonano…"></label>
        <label class="field"><span>Modelo</span><input name="model_name" value="{{ old('model_name') }}"></label>

        <fieldset class="space-y-4" id="trailerSpecs">
            <legend class="text-sm font-semibold">Datos del chasis / tanque</legend>
            <label class="field"><span>Capacidad (L)</span><input name="specs[capacity_l]" type="number" min="0" value="{{ old('specs.capacity_l') }}"></label>
            <label class="field"><span>Compartimentos</span><input name="specs[compartments]" type="number" min="0" max="20" value="{{ old('specs.compartments') }}"></label>
            <label class="field"><span>Material</span><input name="specs[material]" value="{{ old('specs.material') }}" placeholder="Acero, aluminio…"></label>
            <label class="field"><span>Producto</span><input name="specs[product]" value="{{ old('specs.product') }}" placeholder="Combustible, alcohol…"></label>
            <label class="field"><span>Suspensión</span>
                <select name="specs[suspension]">
                    <option value="">Sin especificar</option>
                    <option value="neumatica" @selected(old('specs.suspension')==='neumatica')>Neumática</option>
                    <option value="mecanica" @selected(old('specs.suspension')==='mecanica')>Mecánica</option>
                    <option value="mixta" @selected(old('specs.suspension')==='mixta')>Mixta</option>
                </select>
            </label>
            <label class="field"><span>Cubiertas lineales</span>
                <select name="specs[tire_width]" required>
                    <option value="">Elegir medida</option>
                    <option value="295" @selected(old('specs.tire_width')==='295')>295 — lineal</option>
                    <option value="385" @selected(old('specs.tire_width')==='385')>385 — lineal (gomón)</option>
                </select>
                <span class="hint">Tanques, semis y bateas llevan una cubierta por lado. La medida es de la unidad: 295 o 385.</span>
            </label>
        </fieldset>
        <button class="btn btn-primary">Guardar</button>
    </div>
</form>
@endsection
