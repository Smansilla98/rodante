@extends('layouts.app')
@section('kicker', 'Búsqueda')
@section('title', 'Resultados')
@section('content')
<x-page-header kicker="Búsqueda" :title="'Resultados para “'.$term.'”'" subtitle="Patentes y números de cubierta de tu flota o base." :crumbs="[
    ['label' => 'Tablero', 'url' => route('dashboard')],
    ['label' => 'Búsqueda'],
]"/>

<div class="grid lg:grid-cols-2 gap-5">
    <x-panel title="Unidades" :flush="true">
        <x-content-table :small="true">
            <thead>
                <tr>
                    <th scope="col">Patente</th>
                    <th scope="col">Tipo</th>
                </tr>
            </thead>
            <tbody>
            @forelse($units as $unit)
                <tr>
                    <td><a href="{{ route('units.show', $unit) }}">{{ $unit->plate }}</a></td>
                    <td>{{ $unit->type?->name }} · {{ $unit->fleet?->name }}</td>
                </tr>
            @empty
                <tr><td colspan="2"><x-empty title="Ninguna patente coincide" /></td></tr>
            @endforelse
            </tbody>
        </x-content-table>
    </x-panel>
    <x-panel title="Cubiertas" :flush="true">
        <x-content-table :small="true">
            <thead>
                <tr>
                    <th scope="col">Cubierta</th>
                    <th scope="col">Estado</th>
                </tr>
            </thead>
            <tbody>
            @forelse($tires as $tire)
                <tr>
                    <td><a href="{{ route('tires.show', $tire) }}">{{ $tire->displayName() }}</a></td>
                    <td>
                        <x-status :tone="$tire->status->tone()">{{ $tire->status->label() }}</x-status>
                        <x-status :tone="$tire->condition->tone()">{{ $tire->condition->label() }}</x-status>
                    </td>
                </tr>
            @empty
                <tr><td colspan="2"><x-empty title="Ningún número coincide" /></td></tr>
            @endforelse
            </tbody>
        </x-content-table>
    </x-panel>
</div>
@endsection
