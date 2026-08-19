@extends('layouts.app')
@section('title', 'Costos')
@section('content')
<x-page-header kicker="Consulta" title="Costos" :subtitle="'Total listado: $ '.number_format($total, 2, ',', '.')" />
<x-panel :flush="true">
    <x-content-table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Categoría</th>
                <th>Cubierta</th>
                <th>Importe</th>
                <th>Notas</th>
            </tr>
        </thead>
        <tbody>
        @forelse($entries as $entry)
            <tr>
                <td class="mono">{{ $entry->occurred_at->format('d/m/Y H:i') }}</td>
                <td>{{ $entry->categoryLabel() }}</td>
                <td>{{ $entry->tire?->displayName() ?? '—' }}</td>
                <td class="mono">$ {{ number_format($entry->amount, 2, ',', '.') }}</td>
                <td>{{ $entry->notes }}</td>
            </tr>
        @empty
            <tr><td colspan="5"><x-empty title="Sin costos registrados" text="Aparecen al confirmar compras o cerrar órdenes de trabajo." /></td></tr>
        @endforelse
        </tbody>
    </x-content-table>
    <div class="pager">{{ $entries->links() }}</div>
</x-panel>
@endsection
