@extends('layouts.app')
@section('kicker', 'Catálogo')
@section('title', 'Medidas')
@section('content')
@php $editing = $sizes->firstWhere('id', (int) request('edit')); @endphp
<x-page-header kicker="Catálogo" title="Medidas" subtitle="385/90 R22.5 puede llevar alias Gomón." />
<x-panel :title="$editing ? 'Modificar medida' : 'Nueva medida'">
    <form method="POST" action="{{ $editing ? route('sizes.update', $editing) : route('sizes.store') }}" class="toolbar">
        @csrf
        @if($editing) @method('PUT') @endif
        <input name="code" value="{{ old('code', $editing?->code) }}" placeholder="385/90 R22.5" required>
        <input name="alias" value="{{ old('alias', $editing?->alias) }}" placeholder="Alias (Gomón)">
        <input name="uneven_wear_threshold_mm" type="number" min="1" max="20" value="{{ old('uneven_wear_threshold_mm', $editing?->uneven_wear_threshold_mm ?? 3) }}" placeholder="Umbral mm">
        @if($editing)
            <label class="abm-check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing->is_active))> Activa</label>
        @endif
        <x-abm-actions :editing="(bool) $editing" :cancel="route('sizes.index')" />
    </form>
    @if($editing)
        <div class="abm-actions mt-3">
            <x-abm-delete :action="route('sizes.destroy', $editing)" confirm="¿Eliminar esta medida?" />
        </div>
    @endif
</x-panel>
<x-panel title="Listado" :flush="true" class="mt-5">
    <x-content-table>
        <thead>
            <tr>
                <th scope="col">Medida</th>
                <th scope="col">Umbral lateral</th>
                <th scope="col" class="text-right"> </th>
            </tr>
        </thead>
        <tbody>
        @forelse($sizes as $size)
            <tr>
                <td>{{ $size->displayName() }}</td>
                <td class="mono">{{ $size->uneven_wear_threshold_mm }} mm</td>
                <td class="text-right"><a class="abm-link" href="{{ route('sizes.index', ['edit' => $size->id]) }}">Editar</a></td>
            </tr>
        @empty
            <tr><td colspan="3"><x-empty title="No hay medidas" /></td></tr>
        @endforelse
        </tbody>
    </x-content-table>
</x-panel>
@endsection
