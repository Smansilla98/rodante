@extends('layouts.app')
@section('kicker', 'Catálogo')
@section('title', 'Bases')
@section('content')
<x-page-header kicker="Catálogo" title="Bases" />
<x-panel title="Agregar">
    <form method="POST" action="{{ route('bases.store') }}" class="toolbar">
        @csrf
        <input name="name" placeholder="Nombre" required>
        <input name="code" placeholder="Código" required>
        <input name="location" placeholder="Ubicación">
        <button class="btn btn-dark btn-sm">Agregar</button>
    </form>
</x-panel>
<x-panel title="Listado" :flush="true" class="mt-5">
    @forelse($bases as $base)
        <div class="list-row px-5">
            <span>{{ $base->name }}</span>
            <span class="text-slate-500">{{ $base->location }}</span>
        </div>
    @empty
        <x-empty title="No hay bases" />
    @endforelse
</x-panel>
@endsection
