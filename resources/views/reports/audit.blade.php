@extends('layouts.app')
@section('kicker', 'Consulta')
@section('title', 'Movimientos')
@section('content')
<x-page-header
    kicker="Consulta"
    title="Movimientos"
    subtitle="Historial de la flota: montajes, rotaciones, acoples, mediciones y compras."
>
    <x-slot:actions>
        <x-export-csv :href="route('exports.audit')" />
    </x-slot:actions>
</x-page-header><x-panel :flush="true">
    <x-content-table>
        <thead>
            <tr>
                <th scope="col">Fecha</th>
                <th scope="col">Quién</th>
                <th scope="col">Qué pasó</th>
                <th scope="col">Detalle</th>
            </tr>
        </thead>
        <tbody>
        @forelse($logs as $log)
            <tr>
                <td class="mono">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                <td>{{ $log->user?->name ?? 'Sistema' }}</td>
                <td>{{ $log->actionLabel() }}</td>
                <td>{{ $log->detail() }}</td>
            </tr>
        @empty
            <tr><td colspan="4"><x-empty title="Todavía no hay movimientos registrados" /></td></tr>
        @endforelse
        </tbody>
    </x-content-table>
    <div class="pager">{{ $logs->links() }}</div>
</x-panel>
@endsection
