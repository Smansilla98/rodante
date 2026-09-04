@extends('layouts.app')
@section('kicker', 'Consulta')
@section('title', 'Kilómetros por cubierta')
@section('content')
<x-page-header kicker="Consulta" title="Kilómetros por cubierta" subtitle="Km acumulados, vidas, recapados y reparaciones.">
    <x-slot:actions>
        <x-export-csv :href="route('exports.report-kilometers')" />
    </x-slot:actions>
</x-page-header>
<x-panel :flush="true">
    <x-content-table>
        <thead>
            <tr>
                <th scope="col">Cubierta</th>
                <th scope="col">Km</th>
                <th scope="col">Vidas</th>
                <th scope="col">Recapados</th>
                <th scope="col">Reparaciones</th>
            </tr>
        </thead>
        <tbody>
        @forelse($tires as $tire)
            <tr>
                <td><a href="{{ route('tires.show', $tire) }}">{{ $tire->displayName() }}</a></td>
                <td class="mono">{{ number_format($tire->accumulated_km) }}</td>
                <td class="mono">{{ $tire->lifecycles_count }}</td>
                <td class="mono">{{ $tire->recaps_count }}</td>
                <td class="mono">{{ $tire->repairs_count }}</td>
            </tr>
        @empty
            <tr><td colspan="5"><x-empty title="Sin datos" text="Cuando haya cubiertas, aparecen acá con sus kilómetros." /></td></tr>
        @endforelse
        </tbody>
    </x-content-table>
    <div class="pager">{{ $tires->links() }}</div>
</x-panel>
@endsection
