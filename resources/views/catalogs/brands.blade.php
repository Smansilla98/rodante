@extends('layouts.app')
@section('kicker', 'Catálogo')
@section('title', 'Marcas')
@section('content')
@php $editing = $brands->firstWhere('id', (int) request('edit')); @endphp
<x-page-header kicker="Catálogo" title="Marcas" subtitle="Pirelli, Michelin, Fate… no van fijas en el código." />
<x-panel :title="$editing ? 'Modificar marca' : 'Nueva marca'">
    <form method="POST" action="{{ $editing ? route('brands.update', $editing) : route('brands.store') }}" class="toolbar">
        @csrf
        @if($editing) @method('PUT') @endif
        <input name="name" value="{{ old('name', $editing?->name) }}" placeholder="Nombre" required>
        @if($editing)
            <label class="abm-check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing->is_active))> Activa</label>
        @endif
        <x-abm-actions :editing="(bool) $editing" :cancel="route('brands.index')" />
    </form>
    @if($editing)
        <div class="abm-actions mt-3">
            <x-abm-delete :action="route('brands.destroy', $editing)" confirm="¿Eliminar esta marca?" />
        </div>
    @endif
</x-panel>
<x-panel title="Listado" :flush="true" class="mt-5">
    <x-content-table>
        <thead>
            <tr>
                <th scope="col">Marca</th>
                <th scope="col">Modelos</th>
                <th scope="col">Estado</th>
                <th scope="col" class="text-right"> </th>
            </tr>
        </thead>
        <tbody>
        @forelse($brands as $brand)
            <tr>
                <td>{{ $brand->name }}</td>
                <td class="mono">{{ $brand->models_count }}</td>
                <td>{{ $brand->is_active ? 'Activa' : 'Inactiva' }}</td>
                <td class="text-right"><a class="abm-link" href="{{ route('brands.index', ['edit' => $brand->id]) }}">Editar</a></td>
            </tr>
        @empty
            <tr><td colspan="4"><x-empty title="No hay marcas" /></td></tr>
        @endforelse
        </tbody>
    </x-content-table>
</x-panel>
@endsection
