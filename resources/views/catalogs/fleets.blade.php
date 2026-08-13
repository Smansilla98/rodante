@extends('layouts.app')
@section('kicker', 'Catálogo')
@section('title', 'Flotas')
@section('content')
<x-page-header kicker="Catálogo" title="Flotas" />
<x-panel title="Agregar">
    <form method="POST" action="{{ route('fleets.store') }}" class="grid md:grid-cols-3 gap-2">
        @csrf
        <input name="name" class="inp" placeholder="Nombre" required>
        <input name="code" class="inp" placeholder="Código" required>
        <button class="btn btn-dark">Agregar</button>
    </form>
</x-panel>
<x-panel title="Listado" :flush="true" class="mt-5">
    @forelse($fleets as $fleet)
        <div class="list-row px-5">
            <span>{{ $fleet->name }}</span>
            <span class="mono text-slate-500">{{ $fleet->code }}</span>
        </div>
    @empty
        <x-empty title="No hay flotas" />
    @endforelse
</x-panel>
@endsection
