@extends('layouts.app')
@section('kicker', 'Catálogo')
@section('title', 'Tipos y motivos')
@section('content')
<x-page-header kicker="Catálogo" title="Tipos y motivos" subtitle="Tractor, tanque, batea y motivos de retiro o baja." />
<div class="grid md:grid-cols-2 gap-5">
    <div>
        <x-panel title="Nuevo tipo">
            <form method="POST" action="{{ route('types.store') }}" class="space-y-3">
                @csrf
                <label class="field"><span>Código</span><input name="code"></label>
                <label class="field"><span>Nombre</span><input name="name"></label>
                <label class="flex gap-2 items-center text-sm"><input type="checkbox" name="has_odometer" value="1"> Tiene odómetro</label>
                <button class="btn btn-dark btn-sm">Agregar tipo</button>
            </form>
        </x-panel>
        <x-panel title="Tipos" :flush="true" class="mt-5">
            @foreach($types as $type)
                <div class="list-row px-5">
                    <span>{{ $type->name }}</span>
                    <span class="text-slate-500">{{ $type->has_odometer ? 'Odómetro' : 'Usa tractor' }}</span>
                </div>
            @endforeach
        </x-panel>
    </div>
    <div>
        <x-panel title="Nuevo motivo">
            <form method="POST" action="{{ route('reasons.store') }}" class="space-y-3">
                @csrf
                <label class="field"><span>Código</span><input name="code"></label>
                <label class="field"><span>Nombre</span><input name="name"></label>
                <label class="field"><span>Aplica a</span>
                    <select name="applies_to"><option>RETIRO</option><option>BAJA</option><option>OTRO</option></select>
                </label>
                <button class="btn btn-dark btn-sm">Agregar motivo</button>
            </form>
        </x-panel>
        <x-panel title="Motivos" :flush="true" class="mt-5">
            @foreach($reasons as $reason)
                <div class="list-row px-5">
                    <span>{{ $reason->name }}</span>
                    <span class="text-slate-500">{{ $reason->applies_to }}</span>
                </div>
            @endforeach
        </x-panel>
    </div>
</div>
@endsection
