@extends('layouts.app')
@section('kicker', 'Catálogo')
@section('title', 'Proveedores')
@section('content')
@php $editing = $suppliers->firstWhere('id', (int) request('edit')); @endphp
<x-page-header kicker="Catálogo" title="Proveedores" />
<x-panel :title="$editing ? 'Modificar proveedor' : 'Nuevo proveedor'">
    <form method="POST" action="{{ $editing ? route('suppliers.update', $editing) : route('suppliers.store') }}" class="toolbar">
        @csrf
        @if($editing) @method('PUT') @endif
        <label class="field"><span>Nombre</span>
            <input name="name" class="inp" value="{{ old('name', $editing?->name) }}" required>
            <x-field-error name="name" />
        </label>
        <label class="field"><span>CUIT</span>
            <input name="tax_id" class="inp" value="{{ old('tax_id', $editing?->tax_id) }}">
        </label>
        <label class="field"><span>Teléfono</span>
            <input name="phone" class="inp" value="{{ old('phone', $editing?->phone) }}">
        </label>
        @if($editing)
            <label class="abm-check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing->is_active))> Activo</label>
        @endif
        <x-abm-actions :editing="(bool) $editing" :cancel="route('suppliers.index')" />
    </form>
    @if($editing)
        <div class="abm-actions mt-3">
            <x-abm-delete :action="route('suppliers.destroy', $editing)" confirm="¿Eliminar este proveedor?" />
        </div>
    @endif
</x-panel>
<x-panel title="Listado" :flush="true" class="mt-5">
    <x-content-table>
        <thead>
            <tr>
                <th scope="col">Proveedor</th>
                <th scope="col">CUIT</th>
                <th scope="col" class="text-right"> </th>
            </tr>
        </thead>
        <tbody>
        @forelse($suppliers as $supplier)
            <tr>
                <td>{{ $supplier->name }}</td>
                <td class="mono">{{ $supplier->tax_id }}</td>
                <td class="text-right"><a class="abm-link" href="{{ route('suppliers.index', ['edit' => $supplier->id]) }}">Editar</a></td>
            </tr>
        @empty
            <tr><td colspan="3"><x-empty title="No hay proveedores" action="Nuevo proveedor" :href="route('suppliers.index')" /></td></tr>
        @endforelse
        </tbody>
    </x-content-table>
</x-panel>
@endsection
