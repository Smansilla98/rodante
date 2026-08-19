@extends('layouts.app')
@section('title', $order->number)
@section('content')
<x-page-header kicker="Taller" :title="$order->number" :subtitle="$order->type->label().' · '.$order->shop->name">
    <x-slot:actions>
        <a href="{{ route('work-orders.print', $order) }}" class="btn btn-ghost" target="_blank">Imprimir</a>
        <a href="{{ route('work-orders.index') }}" class="btn btn-ghost">Listado</a>
    </x-slot:actions>
</x-page-header>
<div class="grid lg:grid-cols-2 gap-5">
    <x-panel title="Orden">
        <div class="dl">
            <div><span>Estado</span>{{ $order->status->label() }}</div>
            <div><span>Cubierta</span><a href="{{ route('tires.show', $order->tire) }}">{{ $order->tire->displayName() }}</a></div>
            <div><span>Taller</span>{{ $order->shop->name }}</div>
            <div><span>Abierta por</span>{{ $order->opener?->name }}</div>
            <div><span>Costo</span>{{ $order->cost !== null ? '$ '.number_format($order->cost, 2, ',', '.') : '—' }}</div>
            <div><span>Notas</span>{{ $order->notes ?: '—' }}</div>
        </div>
    </x-panel>
    @if(auth()->user()->role->canWrite() && $order->status->isOpen())
        <x-panel title="Acciones">
            @if($order->status->value === 'ABIERTA')
                <form method="POST" action="{{ route('work-orders.send', $order) }}" class="mb-4" data-confirm="La cubierta pasa a taller (EN_REPARACION). ¿Continuar?">
                    @csrf
                    <button class="btn btn-dark">Enviar al taller</button>
                </form>
            @endif
            <form method="POST" action="{{ route('work-orders.close', $order) }}" class="space-y-3 mb-4" data-confirm="Al cerrar, el recapado abre una vida nueva y la reparación (parche) no. ¿Cerrar?">
                @csrf
                <label class="field"><span>Costo</span><input name="cost" type="number" min="0" step="0.01" class="inp"></label>
                <label class="field"><span>Notas de cierre</span><textarea name="notes" rows="2" class="inp"></textarea></label>
                <button class="btn btn-primary">Cerrar orden</button>
            </form>
            <form method="POST" action="{{ route('work-orders.cancel', $order) }}" data-confirm="¿Cancelar esta orden?">
                @csrf
                <label class="field"><span>Motivo</span><input name="notes" class="inp"></label>
                <button class="btn btn-ghost mt-2">Cancelar</button>
            </form>
        </x-panel>
    @endif
</div>
@endsection
