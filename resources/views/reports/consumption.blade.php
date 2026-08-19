@extends('layouts.app')
@section('kicker', 'Consulta')
@section('title', 'Consumo por modelo')
@section('content')
<x-page-header kicker="Consulta" title="Consumo por modelo" subtitle="Compradas, instaladas, stock y km promedio." />
<x-panel :flush="true">
    <x-content-table>
        <thead>
            <tr>
                <th scope="col">Marca</th>
                <th scope="col">Modelo</th>
                <th scope="col">Compradas</th>
                <th scope="col">Instaladas</th>
                <th scope="col">Stock</th>
                <th scope="col">Baja</th>
                <th scope="col">Km promedio</th>
            </tr>
        </thead>
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
    </x-content-table>
    <div class="pager">{{ $rows->links() }}</div>
</x-panel>
@endsection
