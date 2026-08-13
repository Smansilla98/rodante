@extends('layouts.app')
@section('kicker', 'Inicio')
@section('title', 'Tablero')
@section('content')
@php $s = $stats; @endphp

<x-page-header
    kicker="Operación"
    title="Buenos días, {{ auth()->user()->name }}."
    subtitle="Estado actual de cubiertas, stock y odómetros."
/>

<div class="kpi-grid mb-8">
    <a class="kpi" href="{{ route('tires.index') }}">
        <div class="kpi__l">Total</div>
        <div class="kpi__v">{{ $s['total'] }}</div>
    </a>
    <a class="kpi" href="{{ route('tires.stock') }}">
        <div class="kpi__l">Stock</div>
        <div class="kpi__v">{{ $s['by_status']['STOCK'] ?? 0 }}</div>
    </a>
    <a class="kpi" href="{{ route('tires.index', ['status' => 'INSTALADA']) }}">
        <div class="kpi__l">Instaladas</div>
        <div class="kpi__v">{{ $s['by_status']['INSTALADA'] ?? 0 }}</div>
    </a>
    <a class="kpi" href="{{ route('tires.index', ['status' => 'RESERVA']) }}">
        <div class="kpi__l">Reserva</div>
        <div class="kpi__v">{{ $s['by_status']['RESERVA'] ?? 0 }}</div>
    </a>
    <a class="kpi" href="{{ route('tires.index', ['status' => 'AUXILIO']) }}">
        <div class="kpi__l">Auxilio</div>
        <div class="kpi__v">{{ $s['by_status']['AUXILIO'] ?? 0 }}</div>
    </a>
    <a class="kpi" href="{{ route('tires.index', ['status' => 'EN_REPARACION']) }}">
        <div class="kpi__l">Reparación</div>
        <div class="kpi__v">{{ $s['by_status']['EN_REPARACION'] ?? 0 }}</div>
    </a>
    <a class="kpi" href="{{ route('tires.index', ['status' => 'DE_BAJA']) }}">
        <div class="kpi__l">De baja</div>
        <div class="kpi__v">{{ $s['by_status']['DE_BAJA'] ?? 0 }}</div>
    </a>
    <a class="kpi" href="{{ route('reports.kilometers') }}">
        <div class="kpi__l">Km acumulados</div>
        <div class="kpi__v">{{ number_format($s['km_total']) }}</div>
    </a>
</div>

<div class="hub-grid mb-8">
    <a class="hub" href="{{ route('units.index') }}">
        <div class="hub__n">01</div>
        <div class="hub__t">Unidades</div>
        <div class="hub__s">Planilla tractor + tanque, carga y rotación.</div>
    </a>
    <a class="hub" href="{{ route('tires.stock') }}">
        <div class="hub__n">02</div>
        <div class="hub__t">Stock</div>
        <div class="hub__s">Cubiertas listas para instalar.</div>
    </a>
    <a class="hub" href="{{ route('purchases.create') }}">
        <div class="hub__n">03</div>
        <div class="hub__t">Nueva compra</div>
        <div class="hub__s">Ingreso por número individual.</div>
    </a>
    <a class="hub" href="{{ route('odometers.index') }}">
        <div class="hub__n">04</div>
        <div class="hub__t">Odómetros</div>
        <div class="hub__s">Validar lecturas de jefe o logística.</div>
    </a>
</div>

<div class="grid lg:grid-cols-2 gap-5">
    <x-panel title="Por marca" :flush="true">
        @forelse($s['by_brand'] as $row)
            <div class="list-row px-5">
                <span>{{ $row->name }}</span>
                <span class="mono">{{ $row->total }}</span>
            </div>
        @empty
            <x-empty title="Sin cubiertas cargadas" />
        @endforelse
    </x-panel>

    <x-panel title="Próximas a baja" :flush="true">
        @forelse($s['near_retirement'] as $tire)
            <div class="list-row px-5">
                <a href="{{ route('tires.show', $tire) }}">{{ $tire->displayName() }}</a>
                <span class="mono text-slate-500">{{ number_format($tire->accumulated_km) }} km · {{ $tire->current_tread_min ? $tire->current_tread_min.' mm' : 's/med' }}</span>
            </div>
        @empty
            <x-empty title="Nada cerca de baja" text="Cuando una cubierta se desgaste, aparece acá." />
        @endforelse
    </x-panel>

    <x-panel title="Odómetros pendientes" :flush="true">
        <x-slot:toolbar>
            <a class="btn btn-ghost btn-sm" href="{{ route('odometers.index') }}">Ver todos</a>
        </x-slot:toolbar>
        @forelse($pendingOdometers as $reading)
            <div class="list-row px-5">
                <span>{{ $reading->unit->plate }} · {{ number_format($reading->value) }} km</span>
                <x-status tone="amber">Pendiente</x-status>
            </div>
        @empty
            <x-empty title="No hay lecturas pendientes" />
        @endforelse
    </x-panel>

    <x-panel title="Unidades con más incidencias" :flush="true">
        @forelse($s['units_with_incidents'] as $row)
            <div class="list-row px-5">
                <span>{{ $row['plate'] }}</span>
                <span class="mono">{{ $row['total'] }}</span>
            </div>
        @empty
            <x-empty title="Sin incidencias" />
        @endforelse
    </x-panel>
</div>
@endsection
