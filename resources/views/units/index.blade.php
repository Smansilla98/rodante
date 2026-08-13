@extends('layouts.app')
@section('kicker', 'Operación')
@section('title', 'Unidades')
@section('content')
<x-page-header kicker="Operación" title="Unidades" subtitle="Planilla por tractor y acoplado. El historial vive en cada cubierta.">
    <x-slot:actions>
        <a href="{{ route('units.create') }}" class="btn btn-primary">
            <x-icon name="plus" class="w-4 h-4" /> Nueva unidad
        </a>
    </x-slot:actions>
</x-page-header>

<x-panel :flush="true">
    <x-slot:toolbar>
        <form class="toolbar" method="GET">
            <input name="q" value="{{ request('q') }}" placeholder="Buscar patente">
            <select name="fleet_id">
                <option value="">Todas las flotas</option>
                @foreach($fleets as $fleet)
                    <option value="{{ $fleet->id }}" @selected(request('fleet_id')==$fleet->id)>{{ $fleet->name }}</option>
                @endforeach
            </select>
            <button class="btn btn-dark btn-sm">Buscar</button>
        </form>
    </x-slot:toolbar>

    <div class="tbl-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Patente</th>
                    <th>Tipo</th>
                    <th>Config</th>
                    <th>Flota</th>
                    <th>Acoplado</th>
                    <th>Odómetro</th>
                </tr>
            </thead>
            <tbody>
            @forelse($units as $unit)
                <tr>
                    <td><a href="{{ route('units.show', $unit) }}">{{ $unit->plate }}</a></td>
                    <td>{{ $unit->type->name }}</td>
                    <td class="mono">{{ $unit->configuration->code }}</td>
                    <td>{{ $unit->fleet->name }}</td>
                    <td>{{ $unit->currentCouplingAsTractor?->trailer?->plate ?? $unit->currentCouplingAsTrailer?->tractor?->plate ?? '—' }}</td>
                    <td class="mono">{{ $unit->hasOdometer() ? number_format($unit->current_odometer).' km' : 'Usa tractor' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">
                        <x-empty title="No hay unidades" action="Nueva unidad" :href="route('units.create')" />
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pager">{{ $units->links() }}</div>
</x-panel>
@endsection
