@extends('layouts.app')
@section('kicker', 'Catálogo')
@section('title', 'Marcas')
@section('content')
<x-page-header kicker="Catálogo" title="Marcas" subtitle="Pirelli, Michelin, Fate… no van fijas en el código." />
<x-panel title="Agregar">
    <form method="POST" action="{{ route('brands.store') }}" class="toolbar">
        @csrf
        <input name="name" placeholder="Nueva marca" required>
        <button class="btn btn-dark btn-sm">Agregar</button>
    </form>
</x-panel>
<x-panel title="Listado" :flush="true" class="mt-5">
    @forelse($brands as $brand)
        <div class="list-row px-5">
            <span>{{ $brand->name }}</span>
            <span class="text-slate-500">{{ $brand->models_count }} modelos</span>
        </div>
    @empty
        <x-empty title="No hay marcas" />
    @endforelse
</x-panel>
@endsection
