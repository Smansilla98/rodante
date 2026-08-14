@extends('layouts.app')
@section('kicker', 'Catálogo')
@section('title', 'Bases')
@section('content')
@php $editing = $bases->firstWhere('id', (int) request('edit')); @endphp
<x-page-header kicker="Catálogo" title="Bases" />
<x-panel :title="$editing ? 'Modificar base' : 'Nueva base'">
    <form method="POST" action="{{ $editing ? route('bases.update', $editing) : route('bases.store') }}" class="toolbar">
        @csrf
        @if($editing) @method('PUT') @endif
        <input name="name" value="{{ old('name', $editing?->name) }}" placeholder="Nombre" required>
        <input name="code" value="{{ old('code', $editing?->code) }}" placeholder="Código" required>
        <input name="location" value="{{ old('location', $editing?->location) }}" placeholder="Ubicación">
        @if($editing)
            <label class="abm-check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing->is_active))> Activa</label>
        @endif
        <x-abm-actions :editing="(bool) $editing" :cancel="route('bases.index')" />
    </form>
    @if($editing)
        <div class="abm-actions mt-3">
            <x-abm-delete :action="route('bases.destroy', $editing)" confirm="¿Eliminar esta base?" />
        </div>
    @endif
</x-panel>
<x-panel title="Listado" :flush="true" class="mt-5">
    <x-content-table>
        <thead>
            <tr>
                <th scope="col">Base</th>
                <th scope="col">Ubicación</th>
                <th scope="col" class="text-right"> </th>
            </tr>
        </thead>
        <tbody>
        @forelse($bases as $base)
            <tr>
                <td>{{ $base->name }}</td>
                <td>{{ $base->location }}</td>
                <td class="text-right"><a class="abm-link" href="{{ route('bases.index', ['edit' => $base->id]) }}">Editar</a></td>
            </tr>
        @empty
            <tr><td colspan="3"><x-empty title="No hay bases" /></td></tr>
        @endforelse
        </tbody>
    </x-content-table>
</x-panel>
@endsection
