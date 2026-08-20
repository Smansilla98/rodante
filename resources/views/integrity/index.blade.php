@extends('layouts.app')
@section('title', 'Integridad')
@section('content')
<x-page-header kicker="Consulta" title="Integridad" subtitle="Inconsistencias que romperían la trazabilidad. No las ignore." />
<x-panel :flush="true">
    <x-content-table>
        <thead>
            <tr>
                <th>Cubierta</th>
                <th>Código</th>
                <th>Qué pasó</th>
            </tr>
        </thead>
        <tbody>
        @forelse($findings as $row)
            <tr>
                <td><a href="{{ $row['url'] }}">{{ $row['label'] }}</a></td>
                <td class="mono">{{ $row['code'] }}</td>
                <td>{{ $row['message'] }}</td>
            </tr>
        @empty
            <tr><td colspan="3"><x-empty title="Sin inconsistencias" text="Ubicación, assignments y km cuadran con las reglas." /></td></tr>
        @endforelse
        </tbody>
    </x-content-table>
</x-panel>
@endsection
