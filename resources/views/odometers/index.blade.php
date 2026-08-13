@extends('layouts.app')
@section('kicker', 'Operación')
@section('title', 'Odómetros')
@section('content')
<x-page-header kicker="Operación" title="Odómetros" subtitle="Las lecturas quedan pendientes hasta que las valide jefe de sector o logística." />

<x-panel :flush="true">
    <div class="tbl-wrap">
        <table class="tbl">
            <thead><tr><th>Unidad</th><th>Valor</th><th>Estado</th><th>Cargó</th><th></th></tr></thead>
            <tbody>
            @forelse($readings as $reading)
                <tr>
                    <td><a href="{{ route('units.show', $reading->unit) }}">{{ $reading->unit->plate }}</a></td>
                    <td class="mono">{{ number_format($reading->value) }} km</td>
                    <td><x-status :tone="$reading->status->tone()">{{ $reading->status->label() }}</x-status></td>
                    <td>{{ $reading->recorder->name }}</td>
                    <td>
                        @if($reading->status->value === 'PENDING' && auth()->user()->role->canValidateOdometer())
                            <div class="flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('odometers.validate', $reading) }}">@csrf<button class="btn btn-success btn-sm">Validar</button></form>
                                <form method="POST" action="{{ route('odometers.reject', $reading) }}" class="flex gap-2">
                                    @csrf
                                    <input name="notes" placeholder="Motivo" class="inp" style="min-height:34px;width:140px">
                                    <button class="btn btn-danger btn-sm">Rechazar</button>
                                </form>
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty title="No hay lecturas" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pager">{{ $readings->links() }}</div>
</x-panel>
@endsection
