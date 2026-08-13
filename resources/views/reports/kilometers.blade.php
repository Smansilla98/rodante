@extends('layouts.app')
@section('kicker', 'Consulta')
@section('title', 'Kilómetros por cubierta')
@section('content')
<x-page-header kicker="Consulta" title="Kilómetros por cubierta" subtitle="Km acumulados, vidas, recapados y reparaciones." />
<x-panel :flush="true">
    <div class="tbl-wrap">
        <table class="tbl">
            <thead><tr><th>Cubierta</th><th>Km</th><th>Vidas</th><th>Recapados</th><th>Reparaciones</th></tr></thead>
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
                <tr><td colspan="5"><x-empty title="Sin datos" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-panel>
@endsection
