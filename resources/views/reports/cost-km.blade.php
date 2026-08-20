@extends('layouts.app')
@section('title', 'Costo por km')
@section('content')
<x-page-header kicker="Consulta" title="Costo por kilómetro" subtitle="Compra + taller sobre km acumulados. Sin km, no se calcula." />
<x-panel :flush="true">
    <x-content-table>
        <thead>
            <tr>
                <th>Cubierta</th>
                <th>Km</th>
                <th>Costo</th>
                <th>$ / km</th>
            </tr>
        </thead>
        <tbody>
        @forelse($tires as $tire)
            <tr>
                <td><a href="{{ route('tires.show', $tire) }}">{{ $tire->displayName() }}</a></td>
                <td class="mono">{{ number_format($tire->accumulated_km) }}</td>
                <td class="mono">{{ $tire->cost_total ? '$ '.number_format($tire->cost_total, 2, ',', '.') : '—' }}</td>
                <td class="mono">{{ $tire->cost_per_km !== null ? number_format($tire->cost_per_km, 4, ',', '.') : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="4"><x-empty title="Sin datos" /></td></tr>
        @endforelse
        </tbody>
    </x-content-table>
    <div class="pager">{{ $tires->links() }}</div>
</x-panel>
@endsection
