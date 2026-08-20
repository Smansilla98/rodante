@extends('layouts.app')
@section('title', 'Abrir inventario')
@section('content')
<x-page-header kicker="Depósito" title="Abrir inventario físico" subtitle="Se toma un snapshot de cubiertas en STOCK, reserva y reparación de esa base.">
    <x-slot:actions>
        <a class="btn" href="{{ route('inventories.index') }}">Volver</a>
    </x-slot:actions>
</x-page-header>

<x-panel title="Base a contar">
    <form method="POST" action="{{ route('inventories.store') }}" class="space-y-4" data-loading>
        @csrf
        <x-field-error name="error" />
        <label class="field">
            <span>Base</span>
            <select name="base_id" required>
                <option value="">Elegí una base</option>
                @foreach($bases as $base)
                    <option value="{{ $base->id }}" @selected(old('base_id') == $base->id)>{{ $base->name }}</option>
                @endforeach
            </select>
            <x-field-error name="base_id" />
        </label>
        <label class="field">
            <span>Notas</span>
            <textarea name="notes" rows="3" placeholder="Ej. conteo mensual depósito San Lorenzo">{{ old('notes') }}</textarea>
        </label>
        <button class="btn btn-dark">Abrir e instantánea</button>
    </form>
</x-panel>
@endsection
