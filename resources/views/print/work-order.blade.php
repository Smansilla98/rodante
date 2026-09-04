@extends('layouts.print')
@section('title', $order->number)
@section('back', route('work-orders.show', $order))
@section('reference', $order->number)
@section('document', 'Orden de trabajo')
@section('subtitle', $order->type->label().' · '.($order->shop?->name ?? 'Sin taller'))
@section('code', $order->number)
@section('body')
<section class="section">
    <h2>Datos de la orden</h2>
    <div class="facts">
        <div><span>Estado</span><div>{{ $order->status->label() }}</div></div>
        <div><span>Tipo</span><div>{{ $order->type->label() }}</div></div>
        <div><span>{{ $order->tiresOnOrder()->count() > 1 ? 'Cubiertas' : 'Cubierta' }}</span><div>{{ $order->tiresOnOrder()->map->displayName()->implode(', ') }}</div></div>
        <div><span>Recapadora</span><div>{{ $order->shop?->name ?? '—' }}</div></div>
        <div><span>Abierta</span><div class="mono">{{ $order->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</div></div>
        <div><span>Abierta por</span><div>{{ $order->opener?->name ?? '—' }}</div></div>
        <div><span>Enviada a taller</span><div class="mono">{{ $order->sent_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</div></div>
        <div><span>Cerrada</span><div class="mono">{{ $order->closed_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</div></div>
        <div><span>Cerrada por</span><div>{{ $order->closer?->name ?? '—' }}</div></div>
        <div><span>Costo</span><div class="mono">{{ $order->cost !== null ? '$ '.number_format($order->cost, 2, ',', '.') : '—' }}</div></div>
        <div><span>Notas</span><div>{{ $order->notes ?: '—' }}</div></div>
    </div>
</section>
@endsection
