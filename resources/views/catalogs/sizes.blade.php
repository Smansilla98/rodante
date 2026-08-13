@extends('layouts.app')
@section('kicker', 'Catálogo')
@section('title', 'Medidas')
@section('content')
<x-page-header kicker="Catálogo" title="Medidas" subtitle="385/90 R22.5 puede llevar alias Gomón." />
<x-panel title="Agregar">
    <form method="POST" action="{{ route('sizes.store') }}" class="toolbar">
        @csrf
        <input name="code" placeholder="385/90 R22.5" required>
        <input name="alias" placeholder="Alias (Gomón)">
        <button class="btn btn-dark btn-sm">Agregar</button>
    </form>
</x-panel>
<x-panel title="Listado" :flush="true" class="mt-5">
    @forelse($sizes as $size)
        <div class="list-row px-5">
            <span>{{ $size->displayName() }}</span>
            <span class="text-slate-500">Umbral lateral {{ $size->uneven_wear_threshold_mm }} mm</span>
        </div>
    @empty
        <x-empty title="No hay medidas" />
    @endforelse
</x-panel>
@endsection
