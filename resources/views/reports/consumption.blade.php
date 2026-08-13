@extends('layouts.app')
@section('kicker', 'Consulta')
@section('title', 'Consumo por modelo')
@section('content')
<x-page-header kicker="Consulta" title="Consumo por modelo" subtitle="Compradas, instaladas, stock y km promedio." />
<x-panel :flush="true">
    <div class="tbl-wrap">
        <table class="tbl">
            <thead><tr><th>Marca</th><th>Modelo</th><th>Compradas</th><th>Instaladas</th><th>Stock</th><th>Baja</th><th>Km promedio</th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row->brand }}</td>
                    <td class="mono">{{ $row->model }}</td>
                    <td class="mono">{{ $row->purchased }}</td>
                    <td class="mono">{{ $row->installed }}</td>
                    <td class="mono">{{ $row->stock }}</td>
                    <td class="mono">{{ $row->retired }}</td>
                    <td class="mono">{{ number_format($row->avg_km) }}</td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty title="Sin datos" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-panel>
@endsection
