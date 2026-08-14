@extends('layouts.app')
@section('kicker', 'Consulta')
@section('title', 'Incidencias')
@section('content')
<x-page-header kicker="Consulta" title="Incidencias" subtitle="Recapados, pinchaduras, desgaste irregular y más." />
<x-panel :flush="true">
    <x-content-table>
        <thead>
            <tr>
                <th scope="col">Tipo</th>
                <th scope="col">Cantidad</th>
                <th scope="col">Neumáticos</th>
            </tr>
        </thead>
        <tbody>
        @forelse($rows as $row)
            <tr>
                <td>{{ $row->type }}</td>
                <td class="mono">{{ $row->total }}</td>
                <td class="mono">{{ $row->tires }}</td>
            </tr>
        @empty
            <tr><td colspan="3"><x-empty title="Sin incidencias" /></td></tr>
        @endforelse
        </tbody>
    </x-content-table>
</x-panel>
@endsection
