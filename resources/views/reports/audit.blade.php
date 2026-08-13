@extends('layouts.app')
@section('kicker', 'Consulta')
@section('title', 'Auditoría')
@section('content')
<x-page-header kicker="Consulta" title="Auditoría" subtitle="Quién hizo qué, y sobre qué entidad." />
<x-panel :flush="true">
    <div class="tbl-wrap">
        <table class="tbl">
            <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Entidad</th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td class="mono">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                    <td>{{ $log->user?->name ?? '—' }}</td>
                    <td>{{ $log->action }}</td>
                    <td>{{ class_basename((string) $log->entity_type) }} #{{ $log->entity_id }}</td>
                </tr>
            @empty
                <tr><td colspan="4"><x-empty title="Sin movimientos de auditoría" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pager">{{ $logs->links() }}</div>
</x-panel>
@endsection
