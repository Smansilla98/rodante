@extends('layouts.app')
@section('kicker', 'Operación')
@section('title', 'Odómetros')
@section('content')
@php $editing = $editing ?? null; @endphp
<x-page-header kicker="Operación" title="Odómetros" subtitle="La lectura se asienta al operar. Si hubo un error, se corrige acá.">
    <x-slot:actions>
        <x-export-csv :href="route('exports.odometers', request()->query())" />
    </x-slot:actions>
</x-page-header>
@if($editing && auth()->user()->role->canValidateOdometer())
    <x-panel title="Corregir lectura de {{ $editing->unit->plate }}">
        <form method="POST" action="{{ route('odometers.update', $editing) }}" class="toolbar">
            @csrf
            @method('PUT')
            <input name="value" type="number" min="0" value="{{ old('value', $editing->value) }}" required aria-label="Kilómetros">
            <input name="notes" value="{{ old('notes', $editing->notes) }}" placeholder="Nota (opcional)">
            <x-abm-actions :editing="true" :cancel="route('odometers.index')" saveLabel="Guardar" />
        </form>
    </x-panel>
@endif

<x-panel title="Lecturas" :flush="true" class="{{ $editing ? 'mt-5' : '' }}">
    <x-content-table>
        <thead>
            <tr>
                <th scope="col">Fecha</th>
                <th scope="col">Unidad</th>
                <th scope="col">Valor</th>
                <th scope="col">Estado</th>
                <th scope="col">Cargó</th>
                <th scope="col" class="text-right"> </th>
            </tr>
        </thead>
        <tbody>
        @forelse($readings as $reading)
            <tr>
                <td class="mono">{{ $reading->recorded_at?->format('d/m/Y H:i') }}</td>
                <td><a href="{{ route('units.show', $reading->unit) }}">{{ $reading->unit->plate }}</a></td>
                <td class="mono">{{ number_format($reading->value) }} km</td>
                <td><x-status :tone="$reading->status->tone()">{{ $reading->status->label() }}</x-status></td>
                <td>{{ $reading->recorder->name }}</td>
                <td class="text-right">
                    @if(auth()->user()->role->canValidateOdometer() && $reading->status->value !== 'REJECTED')
                        <a class="abm-link" href="{{ route('odometers.index', ['edit' => $reading->id]) }}">Editar</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6"><x-empty title="No hay lecturas" /></td></tr>
        @endforelse
        </tbody>
    </x-content-table>
    <div class="pager">{{ $readings->links() }}</div>
</x-panel>
@endsection
