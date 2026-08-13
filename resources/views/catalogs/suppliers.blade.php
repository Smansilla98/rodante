@extends('layouts.app')
@section('kicker', 'Catálogo')
@section('title', 'Proveedores')
@section('content')
<x-page-header kicker="Catálogo" title="Proveedores" />
<x-panel title="Agregar">
    <form method="POST" action="{{ route('suppliers.store') }}" class="toolbar">
        @csrf
        <input name="name" placeholder="Nombre" required>
        <input name="tax_id" placeholder="CUIT">
        <button class="btn btn-dark btn-sm">Agregar</button>
    </form>
</x-panel>
<x-panel title="Listado" :flush="true" class="mt-5">
    @forelse($suppliers as $supplier)
        <div class="list-row px-5"><span>{{ $supplier->name }}</span><span class="mono text-slate-500">{{ $supplier->tax_id }}</span></div>
    @empty
        <x-empty title="No hay proveedores" />
    @endforelse
</x-panel>
@endsection
