@extends('layouts.app')
@section('title', 'Inventarios físicos')
@section('content')
<x-page-header kicker="Depósito" title="Inventarios físicos" subtitle="Conteo por base, diferencias y ajuste autorizado. El listado teórico sigue en Reportes.">
    @can('create', App\Models\InventorySession::class)
        <x-slot:actions>
            <a class="btn btn-dark" href="{{ route('inventories.create') }}">Abrir inventario</a>
        </x-slot:actions>
    @endcan
</x-page-header>

<x-panel :flush="true">
    <x-content-table>
        <thead>
            <tr>
                <th>Número</th>
                <th>Base</th>
                <th>Estado</th>
                <th>Esperadas</th>
                <th>Contadas</th>
                <th>Faltantes</th>
                <th>Abierta</th>
            </tr>
        </thead>
        <tbody>
        @forelse($sessions as $session)
            <tr>
                <td><a href="{{ route('inventories.show', $session) }}" class="mono">{{ $session->number }}</a></td>
                <td>{{ $session->base?->name }}</td>
                <td>{{ $session->status->label() }}</td>
                <td class="mono">{{ $session->expected_count }}</td>
                <td class="mono">{{ $session->found_count }}</td>
                <td class="mono">{{ $session->missing_count }}</td>
                <td class="mono">{{ $session->opened_at?->format('d/m/Y H:i') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7">
                    <x-empty
                        title="Sin inventarios"
                        text="Abrí uno por base para contrastar el sistema con el depósito real."
                        :action="auth()->user()->role->canWrite() ? 'Abrir inventario' : null"
                        :href="auth()->user()->role->canWrite() ? route('inventories.create') : null"
                    />
                </td>
            </tr>
        @endforelse
        </tbody>
    </x-content-table>
    <div class="pager">{{ $sessions->links() }}</div>
</x-panel>
@endsection
