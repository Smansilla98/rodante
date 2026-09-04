@extends('layouts.app')
@section('kicker', 'Consulta')
@section('title', 'Incidencias')
@section('content')
<x-page-header kicker="Consulta" title="Incidencias" subtitle="Eventos cargados sobre cubiertas, filtrables por tipo y unidad.">
    <x-slot:actions>
        <x-export-csv :href="route('exports.incidents', request()->query())" />
        <a href="{{ route('reports.incidents') }}" class="btn btn-ghost">Resumen por tipo</a>
    </x-slot:actions>
</x-page-header>

<x-panel :flush="true">
    <x-slot:toolbar>
        <form class="toolbar" method="GET">
            <select name="type" aria-label="Tipo">
                <option value="">Tipo</option>
                @foreach($types as $type)
                    <option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
            <select name="unit_id" aria-label="Unidad">
                <option value="">Unidad</option>
                @foreach($units as $unit)
                    <option value="{{ $unit->id }}" @selected((string) request('unit_id') === (string) $unit->id)>{{ $unit->plate }}</option>
                @endforeach
            </select>
            <select name="fleet_id" aria-label="Flota">
                <option value="">Flota</option>
                @foreach($fleets as $fleet)
                    <option value="{{ $fleet->id }}" @selected((string) request('fleet_id') === (string) $fleet->id)>{{ $fleet->name }}</option>
                @endforeach
            </select>
            <select name="base_id" aria-label="Base">
                <option value="">Base</option>
                @foreach($bases as $base)
                    <option value="{{ $base->id }}" @selected((string) request('base_id') === (string) $base->id)>{{ $base->name }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ request('from') }}" aria-label="Desde">
            <input type="date" name="to" value="{{ request('to') }}" aria-label="Hasta">
            <button class="btn btn-dark btn-sm">Filtrar</button>
        </form>
    </x-slot:toolbar>
    <x-content-table>
        <thead>
            <tr>
                <th scope="col">Fecha</th>
                <th scope="col">Tipo</th>
                <th scope="col">Cubierta</th>
                <th scope="col">Unidad</th>
                <th scope="col">Detalle</th>
            </tr>
        </thead>
        <tbody>
        @forelse($incidents as $incident)
            <tr>
                <td class="mono">{{ $incident->occurred_at?->format('d/m/Y H:i') }}</td>
                <td>{{ $incident->type->label() }}</td>
                <td><a href="{{ route('tires.show', $incident->tire) }}">{{ $incident->tire?->displayName() }}</a></td>
                <td>
                    @if($incident->unit)
                        <a href="{{ route('units.show', $incident->unit) }}">{{ $incident->unit->plate }}</a>
                    @else
                        —
                    @endif
                </td>
                <td class="text-sm">{{ $incident->description ?: ($incident->notes ?: '—') }}</td>
            </tr>
        @empty
            <tr><td colspan="5"><x-empty title="Sin incidencias" text="Cuando se registren eventos, aparecen acá." /></td></tr>
        @endforelse
        </tbody>
    </x-content-table>
    <div class="pager">{{ $incidents->links() }}</div>
</x-panel>
@endsection
