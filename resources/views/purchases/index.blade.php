@extends('layouts.app')
@section('kicker', 'Operación')
@section('title', 'Compras')
@section('content')
<x-page-header kicker="Operación" title="Compras" subtitle="Cada línea genera números individuales consecutivos.">
    <x-slot:actions>
        <a href="{{ route('purchases.create') }}" class="btn btn-primary"><x-icon name="plus" class="w-4 h-4" /> Nueva compra</a>
    </x-slot:actions>
</x-page-header>

<x-panel :flush="true">
    <div class="tbl-wrap">
        <table class="tbl">
            <thead><tr><th>Número</th><th>Proveedor</th><th>Base</th><th>Fecha</th><th>Estado</th></tr></thead>
            <tbody>
            @forelse($purchases as $purchase)
                <tr>
                    <td><a href="{{ route('purchases.show', $purchase) }}">{{ $purchase->number }}</a></td>
                    <td>{{ $purchase->supplier->name }}</td>
                    <td>{{ $purchase->base->name }}</td>
                    <td class="mono">{{ $purchase->purchased_at->format('d/m/Y') }}</td>
                    <td>
                        <x-status :tone="$purchase->isConfirmed() ? 'green' : 'amber'">
                            {{ $purchase->isConfirmed() ? 'Confirmada' : 'Borrador' }}
                        </x-status>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty title="No hay compras" action="Nueva compra" :href="route('purchases.create')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pager">{{ $purchases->links() }}</div>
</x-panel>
@endsection
