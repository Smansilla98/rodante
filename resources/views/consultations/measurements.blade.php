@extends('layouts.app')
@section('kicker', 'Consulta')
@section('title', 'Mediciones')
@section('content')
<x-page-header kicker="Consulta" title="Mediciones" subtitle="Profundidades cargadas. El desgaste irregular aparece resaltado.">
    <x-slot:actions>
        <x-export-csv :href="route('exports.measurements', request()->query())" />
    </x-slot:actions>
</x-page-header>

<x-panel :flush="true">
    <x-slot:toolbar>
        <form class="toolbar" method="GET">
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
            <input type="number" name="min_mm" step="0.1" min="0" value="{{ request('min_mm') }}" placeholder="mm máx. cubierta" aria-label="Profundidad máxima de cubierta" inputmode="decimal">
            <label class="field field--check"><input type="checkbox" name="alert" value="1" @checked(request()->boolean('alert'))> Solo alertas</label>
            <button class="btn btn-dark btn-sm">Filtrar</button>
        </form>
    </x-slot:toolbar>
    <x-content-table>
        <thead>
            <tr>
                <th scope="col">Fecha</th>
                <th scope="col">Cubierta</th>
                <th scope="col">Unidad</th>
                <th scope="col">Profundidades</th>
                <th scope="col">Alerta</th>
            </tr>
        </thead>
        <tbody>
        @forelse($measurements as $m)
            <tr @class(['row-alert' => $m->raises_alert])>
                <td class="mono">{{ $m->measured_at?->format('d/m/Y H:i') }}</td>
                <td>
                    <a href="{{ route('tires.show', $m->tire) }}">{{ $m->tire?->displayName() }}</a>
                </td>
                <td>
                    @if($m->unit)
                        <a href="{{ route('units.show', $m->unit) }}">{{ $m->unit->plate }}</a>
                    @else
                        —
                    @endif
                </td>
                <td class="mono text-sm">
                    {{ $m->readings->map(fn ($r) => ($r->zone?->name ?? '?').': '.$r->millimeters)->implode(' · ') ?: '—' }}
                </td>
                <td>
                    @if($m->raises_alert)
                        <x-status tone="warn">Desgaste irregular</x-status>
                    @else
                        <x-status tone="ok">OK</x-status>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5"><x-empty title="Sin mediciones" text="Cuando se midan profundidades, aparecen acá." /></td></tr>
        @endforelse
        </tbody>
    </x-content-table>
    <div class="pager">{{ $measurements->links() }}</div>
</x-panel>
@endsection
