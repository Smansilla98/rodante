@extends('layouts.app')
@section('title', $tire->displayName())
@section('content')
@php $forecast = $forecast ?? []; @endphp
<x-page-header kicker="Campo" :title="$tire->displayName()" :subtitle="$tire->status->label()">
    <x-slot:actions>
        <a href="{{ route('field.index') }}" class="btn btn-ghost">Otra cubierta</a>
    </x-slot:actions>
</x-page-header>
<div class="panel max-w-xl">
    <div class="panel__body space-y-4">
        <div class="dl">
            <div><span>Estado</span>{{ $tire->status->label() }}</div>
            <div><span>Condición</span>{{ $tire->condition->label() }}</div>
            <div><span>Km</span><span class="mono">{{ number_format($tire->accumulated_km) }}</span></div>
            @if(!empty($forecast['narrative']))
                <div class="col-span-full">
                    <span>Pronóstico</span>
                    <p>{{ $forecast['narrative'] }}</p>
                </div>
            @endif
            <div>
                <span>Dónde</span>
                @if($tire->currentLocation?->unit)
                    {{ $tire->currentLocation->unit->plate }} · {{ $tire->currentLocation->position?->name }}
                @else
                    {{ $tire->currentLocation?->location_kind?->label() ?? '—' }}
                    @if($tire->currentLocation?->base) · {{ $tire->currentLocation->base->name }} @endif
                @endif
            </div>
        </div>
        <div class="space-y-2">
            <a class="btn btn-primary w-full" href="{{ route('tires.show', $tire) }}">Historia clínica</a>
            <a class="btn btn-dark w-full" href="{{ route('tires.life-report', $tire) }}" target="_blank">Informe de vida</a>
            @if($tire->currentLocation?->unit)
                <a class="btn btn-dark w-full" href="{{ route('units.show', $tire->currentLocation->unit) }}">Abrir planilla</a>
            @endif
            @if(auth()->user()->role->canWrite() && in_array($tire->status->value, ['EN_REPARACION', 'RESERVA'], true))
                <form method="POST" action="{{ route('tires.return-stock', $tire) }}" data-confirm="¿Volver a stock?">
                    @csrf
                    <button class="btn btn-ghost w-full">Devolver a stock</button>
                </form>
            @endif
            <a class="btn btn-ghost w-full" href="{{ route('tires.print', $tire) }}" target="_blank">Imprimir ficha</a>
        </div>
    </div>
</div>
@endsection
