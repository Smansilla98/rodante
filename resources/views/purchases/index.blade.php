@extends('layouts.app')
@section('kicker', 'Operación')
@section('title', 'Compras')
@section('content')
<x-page-header kicker="Operación" title="Compras" subtitle="Cada línea genera números individuales consecutivos.">
    <x-slot:actions>
        @if(auth()->user()->role->canWrite())
            <a href="{{ route('purchases.create') }}" class="btn btn-primary"><x-icon name="plus" class="w-4 h-4" /> Nueva compra</a>
        @endif
    </x-slot:actions>
</x-page-header>

<x-panel :flush="true">
    <x-content-table>
        <thead>
            <tr>
                <th scope="col">Número</th>
                <th scope="col">Proveedor</th>
                <th scope="col">Base</th>
                <th scope="col">Fecha</th>
                <th scope="col">Estado</th>
            </tr>
        </thead>
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
            <tr><td colspan="5"><x-empty title="No hay compras" :action="auth()->user()->role->canWrite() ? 'Nueva compra' : null" :href="route('purchases.create')" /></td></tr>
        @endforelse
        </tbody>
    </x-content-table>
    <div class="pager">{{ $purchases->links() }}</div>
</x-panel>
@endsection
