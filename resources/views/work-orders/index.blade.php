@extends('layouts.app')
@section('title', 'Órdenes de trabajo')
@section('content')
<x-page-header kicker="Taller" title="Órdenes de trabajo" subtitle="Recapado y reparación con recapadora, costos e historial.">
    <x-slot:actions>
        @if(auth()->user()->role->canWrite())
            <a href="{{ route('work-orders.create') }}" class="btn btn-primary">Nueva orden</a>
        @endif
    </x-slot:actions>
</x-page-header>
<x-panel :flush="true">
    <x-content-table>
        <thead>
            <tr>
                <th scope="col">Número</th>
                <th scope="col">Cubierta</th>
                <th scope="col">Tipo</th>
                <th scope="col">Taller</th>
                <th scope="col">Estado</th>
            </tr>
        </thead>
        <tbody>
        @forelse($orders as $order)
            <tr>
                <td><a href="{{ route('work-orders.show', $order) }}">{{ $order->number }}</a></td>
                <td>{{ $order->tire?->displayName() }}</td>
                <td>{{ $order->type->label() }}</td>
                <td>{{ $order->shop?->name }}</td>
                <td>{{ $order->status->label() }}</td>
            </tr>
        @empty
            <tr><td colspan="5"><x-empty title="No hay órdenes" :action="auth()->user()->role->canWrite() ? 'Crear orden' : null" :href="route('work-orders.create')" /></td></tr>
        @endforelse
        </tbody>
    </x-content-table>
    <div class="pager">{{ $orders->links() }}</div>
</x-panel>
@endsection
