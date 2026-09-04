@extends('layouts.app')
@section('kicker', 'Inicio')
@section('title', 'Tablero')
@section('content')
@php
    $s = $stats;
    $kpis = auth()->user()->role->dashboardKpis();
@endphp

<x-page-header
    kicker="Inicio"
    title="Tablero"
    subtitle="Hola, {{ auth()->user()->name }}."
/>

@if(auth()->user()->role->canWrite())
    <div class="grid sm:grid-cols-3 gap-3 mb-8">
        <a href="{{ route('odometers.index') }}" class="btn btn-primary btn-touch">Cargar km</a>
        <a href="{{ route('field.index') }}" class="btn btn-dark btn-touch">Medición / incidencia</a>
        <a href="{{ route('units.index') }}" class="btn btn-ghost btn-touch">Abrir planilla</a>
    </div>
@endif
<div class="kpi-grid mb-8">
    @if(in_array('total', $kpis, true))
    <a class="kpi kpi--blue" href="{{ route('tires.index') }}">
        <span class="kpi__ico" aria-hidden="true"><x-icon name="circle" class="w-6 h-6" /></span>
        <div>
            <div class="kpi__l">Total de cubiertas</div>
            <div class="kpi__v">{{ $s['total'] }}</div>
        </div>
    </a>
    @endif
    @if(in_array('stock', $kpis, true))
    <a class="kpi kpi--indigo" href="{{ route('tires.stock') }}">
        <span class="kpi__ico" aria-hidden="true"><x-icon name="boxes" class="w-6 h-6" /></span>
        <div>
            <div class="kpi__l">En stock</div>
            <div class="kpi__v">{{ $s['by_status']['STOCK'] ?? 0 }}</div>
        </div>
    </a>
    @endif
    @if(in_array('installed', $kpis, true))
    <a class="kpi kpi--teal" href="{{ route('tires.index', ['status' => 'INSTALADA']) }}">
        <span class="kpi__ico" aria-hidden="true"><x-icon name="truck" class="w-6 h-6" /></span>
        <div>
            <div class="kpi__l">Instaladas</div>
            <div class="kpi__v">{{ $s['by_status']['INSTALADA'] ?? 0 }}</div>
        </div>
    </a>
    @endif
    @if(in_array('reserve', $kpis, true))
    <a class="kpi kpi--amber" href="{{ route('tires.index', ['status' => 'RESERVA']) }}">
        <span class="kpi__ico" aria-hidden="true"><x-icon name="inbox" class="w-6 h-6" /></span>
        <div>
            <div class="kpi__l">En reserva</div>
            <div class="kpi__v">{{ $s['by_status']['RESERVA'] ?? 0 }}</div>
        </div>
    </a>
    @endif
    @if(in_array('spare', $kpis, true))
    <a class="kpi kpi--violet" href="{{ route('tires.index', ['status' => 'AUXILIO']) }}">
        <span class="kpi__ico" aria-hidden="true"><x-icon name="grid" class="w-6 h-6" /></span>
        <div>
            <div class="kpi__l">Auxilio</div>
            <div class="kpi__v">{{ $s['by_status']['AUXILIO'] ?? 0 }}</div>
        </div>
    </a>
    @endif
    @if(in_array('repair', $kpis, true))
    <a class="kpi kpi--cyan" href="{{ route('tires.index', ['status' => 'EN_REPARACION']) }}">
        <span class="kpi__ico" aria-hidden="true"><x-icon name="alert" class="w-6 h-6" /></span>
        <div>
            <div class="kpi__l">En reparación</div>
            <div class="kpi__v">{{ $s['by_status']['EN_REPARACION'] ?? 0 }}</div>
        </div>
    </a>
    @endif
    @if(in_array('retired', $kpis, true))
    <a class="kpi kpi--red" href="{{ route('tires.index', ['status' => 'DE_BAJA']) }}">
        <span class="kpi__ico" aria-hidden="true"><x-icon name="shield" class="w-6 h-6" /></span>
        <div>
            <div class="kpi__l">De baja</div>
            <div class="kpi__v">{{ $s['by_status']['DE_BAJA'] ?? 0 }}</div>
        </div>
    </a>
    @endif
    @if(in_array('km', $kpis, true))
    <a class="kpi kpi--slate" href="{{ route('reports.kilometers') }}">
        <span class="kpi__ico" aria-hidden="true"><x-icon name="chart" class="w-6 h-6" /></span>
        <div>
            <div class="kpi__l">Kilómetros acumulados</div>
            <div class="kpi__v">{{ number_format($s['km_total']) }}</div>
        </div>
    </a>
    @endif
</div>

<div class="queue-grid mb-8">
    <a class="queue queue--repair" href="{{ route('tires.index', ['queue' => 'repair']) }}">
        <div class="queue__l">Cola de reparación</div>
        <div class="queue__v">{{ $s['in_repair_count'] }}</div>
        <div class="queue__s">Pinchadura o parche. Vuelven a stock sin recapar.</div>
        @if($s['in_repair']->isNotEmpty())
            <ul class="queue__list">
                @foreach($s['in_repair']->take(4) as $tire)
                    <li>{{ $tire->displayName() }}</li>
                @endforeach
            </ul>
        @endif
    </a>
    <a class="queue queue--tread" href="{{ route('tires.index', ['queue' => 'tread']) }}">
        <div class="queue__l">Profundidad crítica</div>
        <div class="queue__v">{{ $s['critical_tread_count'] }}</div>
        <div class="queue__s">Umbral {{ $s['thresholds']['mm'] }} mm o menos.</div>
        @if($s['critical_tread']->isNotEmpty())
            <ul class="queue__list">
                @foreach($s['critical_tread']->take(4) as $tire)
                    <li>{{ $tire->displayName() }} · {{ $tire->current_tread_min }} mm</li>
                @endforeach
            </ul>
        @endif
    </a>
    <a class="queue queue--retire" href="{{ route('tires.index', ['queue' => 'retirement']) }}">
        <div class="queue__l">Próximas a baja</div>
        <div class="queue__v">{{ $s['near_retirement_count'] }}</div>
        <div class="queue__s">{{ number_format($s['thresholds']['km']) }} km o {{ $s['thresholds']['mm'] }} mm.</div>
        @if($s['near_retirement']->isNotEmpty())
            <ul class="queue__list">
                @foreach($s['near_retirement']->take(4) as $tire)
                    <li>{{ $tire->displayName() }}</li>
                @endforeach
            </ul>
        @endif
    </a>
    @if(auth()->user()->role->canRetireOrRecap())
    <a class="queue queue--retire" href="{{ route('integrity.index') }}">
        <div class="queue__l">Integridad</div>
        <div class="queue__v">{{ $s['integrity_count'] ?? 0 }}</div>
        <div class="queue__s">Ubicación, assignments y km que no cierran.</div>
    </a>
    @endif
</div>

<div class="hub-grid mb-8">
    <a class="hub" href="{{ route('field.index') }}">
        <span class="hub__ico" aria-hidden="true"><x-icon name="search" class="w-6 h-6" /></span>
        <div class="hub__t">Campo</div>
        <div class="hub__s">Número o QR. Identificar y actuar.</div>
    </a>
    <a class="hub" href="{{ route('units.index') }}">
        <span class="hub__ico" aria-hidden="true"><x-icon name="truck" class="w-6 h-6" /></span>
        <div class="hub__t">Unidades</div>
        <div class="hub__s">Planilla tractor + tanque, carga y rotación.</div>
    </a>
    <a class="hub" href="{{ route('tires.stock') }}">
        <span class="hub__ico" aria-hidden="true"><x-icon name="boxes" class="w-6 h-6" /></span>
        <div class="hub__t">Stock</div>
        <div class="hub__s">Cubiertas listas para instalar.</div>
    </a>
    @if(auth()->user()->role->canWrite())
    <a class="hub" href="{{ route('purchases.create') }}">
        <span class="hub__ico" aria-hidden="true"><x-icon name="plus" class="w-6 h-6" /></span>
        <div class="hub__t">Nueva compra</div>
        <div class="hub__s">Ingreso por número individual.</div>
    </a>
    @endif
    <a class="hub" href="{{ route('odometers.index') }}">
        <span class="hub__ico" aria-hidden="true"><x-icon name="gauge" class="w-6 h-6" /></span>
        <div class="hub__t">Odómetros</div>
        <div class="hub__s">Últimas lecturas y corrección de km.</div>
    </a>
    <a class="hub" href="{{ route('reports.predictive') }}">
        <span class="hub__ico" aria-hidden="true"><x-icon name="chart" class="w-6 h-6" /></span>
        <div class="hub__t">Predictivo</div>
        <div class="hub__s">Km estimados hasta 4 mm e informe de vida.</div>
    </a>
    @if(auth()->user()->role->canViewTelemetry())
    <a class="hub" href="{{ route('reports.telemetry') }}">
        <span class="hub__ico" aria-hidden="true"><x-icon name="gauge" class="w-6 h-6" /></span>
        <div class="hub__t">Telemetría</div>
        <div class="hub__s">Ingresos, campo, planilla y bajas de la empresa.</div>
    </a>
    @endif
    <a class="hub" href="{{ route('help.index') }}">
        <span class="hub__ico" aria-hidden="true"><x-icon name="book" class="w-6 h-6" /></span>
        <div class="hub__t">Ayuda</div>
        <div class="hub__s">Qué hace cada parte según tu rol y el manual de uso.</div>
    </a>
</div>

<div class="grid lg:grid-cols-2 gap-5">
    <x-panel title="Por marca" :flush="true">
        <x-content-table :small="true">
            <thead>
                <tr>
                    <th scope="col">Marca</th>
                    <th scope="col" class="text-right">Cubiertas</th>
                </tr>
            </thead>
            <tbody>
            @forelse($s['by_brand'] as $row)
                <tr>
                    <td>{{ $row->name }}</td>
                    <td class="text-right mono">{{ $row->total }}</td>
                </tr>
            @empty
                <tr><td colspan="2"><x-empty title="Sin cubiertas cargadas" :action="auth()->user()->role->canWrite() ? 'Nueva compra' : null" :href="route('purchases.create')" /></td></tr>
            @endforelse
            </tbody>
        </x-content-table>
    </x-panel>

    <x-panel title="Próximas a baja ({{ number_format($s['thresholds']['km']) }} km o {{ $s['thresholds']['mm'] }} mm)" :flush="true">
        <x-slot:toolbar>
            <a class="btn btn-ghost btn-sm" href="{{ route('tires.index', ['queue' => 'retirement']) }}">Ver cola</a>
        </x-slot:toolbar>
        <x-content-table :small="true">
            <thead>
                <tr>
                    <th scope="col">Cubierta</th>
                    <th scope="col" class="text-right">Km / mm</th>
                </tr>
            </thead>
            <tbody>
            @forelse($s['near_retirement'] as $tire)
                <tr>
                    <td><a href="{{ route('tires.show', $tire) }}">{{ $tire->displayName() }}</a></td>
                    <td class="text-right mono">{{ number_format($tire->accumulated_km) }} km · {{ $tire->current_tread_min ? $tire->current_tread_min.' mm' : 's/med' }}</td>
                </tr>
            @empty
                <tr><td colspan="2"><x-empty title="Nada cerca de baja" text="Cuando una cubierta se desgaste, aparece acá." /></td></tr>
            @endforelse
            </tbody>
        </x-content-table>
    </x-panel>

    <x-panel title="Últimas lecturas" :flush="true">
        <x-slot:toolbar>
            <a class="btn btn-ghost btn-sm" href="{{ route('odometers.index') }}">Ver todos</a>
        </x-slot:toolbar>
        <x-content-table :small="true">
            <thead>
                <tr>
                    <th scope="col">Unidad</th>
                    <th scope="col">Lectura</th>
                </tr>
            </thead>
            <tbody>
            @forelse($recentOdometers as $reading)
                <tr>
                    <td>{{ $reading->unit->plate }}</td>
                    <td class="mono">{{ number_format($reading->value) }} km</td>
                </tr>
            @empty
                <tr><td colspan="2"><x-empty title="Todavía no hay lecturas" /></td></tr>
            @endforelse
            </tbody>
        </x-content-table>
    </x-panel>

    <x-panel title="Unidades con más incidencias" :flush="true">
        <x-content-table :small="true">
            <thead>
                <tr>
                    <th scope="col">Patente</th>
                    <th scope="col" class="text-right">Incidencias</th>
                </tr>
            </thead>
            <tbody>
            @forelse($s['units_with_incidents'] as $row)
                <tr>
                    <td><a href="{{ route('units.show', $row['id']) }}">{{ $row['plate'] }}</a></td>
                    <td class="text-right mono">{{ $row['total'] }}</td>
                </tr>
            @empty
                <tr><td colspan="2"><x-empty title="Sin incidencias" /></td></tr>
            @endforelse
            </tbody>
        </x-content-table>
    </x-panel>
</div>
@endsection
