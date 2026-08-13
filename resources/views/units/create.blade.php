@extends('layouts.app')
@section('kicker', 'Operación')
@section('title', 'Nueva unidad')
@section('content')
<x-page-header kicker="Operación" title="Nueva unidad" subtitle="Patente, flota, base y configuración de cubiertas.">
    <x-slot:actions>
        <a href="{{ route('units.index') }}" class="btn btn-ghost"><x-icon name="back" class="w-4 h-4" /> Volver</a>
    </x-slot:actions>
</x-page-header>

<form method="POST" action="{{ route('units.store') }}" class="panel max-w-xl">
    @csrf
    <div class="panel__body space-y-4">
        <label class="field"><span>Patente</span><input name="plate" required></label>
        <label class="field"><span>Flota</span>
            <select name="fleet_id" required>@foreach($fleets as $fleet)<option value="{{ $fleet->id }}">{{ $fleet->name }}</option>@endforeach</select>
        </label>
        <label class="field"><span>Base</span>
            <select name="base_id" required>@foreach($bases as $base)<option value="{{ $base->id }}">{{ $base->name }}</option>@endforeach</select>
        </label>
        <label class="field"><span>Tipo</span>
            <select name="unit_type_id" required>@foreach($types as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach</select>
        </label>
        <label class="field"><span>Configuración</span>
            <select name="unit_configuration_id" required>@foreach($configurations as $cfg)<option value="{{ $cfg->id }}">{{ $cfg->code }} — {{ $cfg->name }}</option>@endforeach</select>
        </label>
        <label class="field"><span>Marca del equipo</span><input name="brand" placeholder="Scania, Volvo…"></label>
        <label class="field"><span>Modelo</span><input name="model_name"></label>
        <label class="field"><span>Odómetro actual</span><input name="current_odometer" type="number" placeholder="Solo tractor"></label>
        <button class="btn btn-primary">Guardar</button>
    </div>
</form>
@endsection
