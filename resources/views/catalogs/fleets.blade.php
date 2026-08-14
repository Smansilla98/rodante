@extends('layouts.app')
@section('kicker', 'Catálogo')
@section('title', 'Flotas')
@section('content')
@php $editing = $fleets->firstWhere('id', (int) request('edit')); @endphp
<x-page-header kicker="Catálogo" title="Flotas" />
<x-panel :title="$editing ? 'Modificar flota' : 'Nueva flota'">
    <form method="POST" action="{{ $editing ? route('fleets.update', $editing) : route('fleets.store') }}" class="space-y-3">
        @csrf
        @if($editing) @method('PUT') @endif
        <div class="toolbar">
            <input name="name" class="inp" value="{{ old('name', $editing?->name) }}" placeholder="Nombre" required>
            <input name="code" class="inp" value="{{ old('code', $editing?->code) }}" placeholder="Código" required>
            @if($editing)
                <label class="abm-check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing->is_active))> Activa</label>
            @endif
        </div>
        @if($bases->isNotEmpty())
            <div class="abm-sizes">
                @foreach($bases as $base)
                    <label class="abm-check">
                        <input type="checkbox" name="base_ids[]" value="{{ $base->id }}" @checked(in_array($base->id, old('base_ids', $editing?->bases?->pluck('id')->all() ?? []), false))>
                        {{ $base->name }}
                    </label>
                @endforeach
            </div>
        @endif
        <x-abm-actions :editing="(bool) $editing" :cancel="route('fleets.index')" />
    </form>
    @if($editing)
        <div class="abm-actions mt-3">
            <x-abm-delete :action="route('fleets.destroy', $editing)" confirm="¿Eliminar esta flota?" />
        </div>
    @endif
</x-panel>
<x-panel title="Listado" :flush="true" class="mt-5">
    <x-content-table>
        <thead>
            <tr>
                <th scope="col">Flota</th>
                <th scope="col">Código</th>
                <th scope="col">Bases</th>
                <th scope="col" class="text-right"> </th>
            </tr>
        </thead>
        <tbody>
        @forelse($fleets as $fleet)
            <tr>
                <td>{{ $fleet->name }}</td>
                <td class="mono">{{ $fleet->code }}</td>
                <td>{{ $fleet->bases->pluck('name')->join(', ') ?: '—' }}</td>
                <td class="text-right"><a class="abm-link" href="{{ route('fleets.index', ['edit' => $fleet->id]) }}">Editar</a></td>
            </tr>
        @empty
            <tr><td colspan="4"><x-empty title="No hay flotas" /></td></tr>
        @endforelse
        </tbody>
    </x-content-table>
</x-panel>
@endsection
