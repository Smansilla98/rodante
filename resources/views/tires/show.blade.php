@extends('layouts.app')
@section('kicker', 'Cubierta')
@section('title', $tire->displayName())
@section('content')
@php
    $loc = $tire->currentLocation;
    $lastMeasure = $tire->measurements->sortByDesc('measured_at')->first();
    $lastEvent = $timeline->first();
    $canReturn = auth()->user()->role->canWrite() && in_array($tire->status->value, ['EN_REPARACION', 'RESERVA'], true);
    $repaired = $tire->condition->value === 'REPARADA';
@endphp
<x-page-header kicker="Cubierta" :title="$tire->displayName()" :subtitle="$tire->fullName().' · '.$tire->size->displayName()" :crumbs="[
    ['label' => 'Tablero', 'url' => route('dashboard')],
    ['label' => 'Neumáticos', 'url' => route('tires.index')],
    ['label' => $tire->displayName()],
]">
    <x-slot:actions>
        <a href="{{ route('tires.print', $tire) }}" class="btn btn-ghost" target="_blank">Imprimir</a>
        <a href="{{ route('tires.index') }}" class="btn btn-ghost"><x-icon name="back" class="w-4 h-4" /> Listado</a>
        @if(auth()->user()->role->canManageAbm())
            @if(request('edit'))
                <a href="{{ route('tires.show', $tire) }}" class="btn btn-ghost">Cancelar</a>
            @else
                <a href="{{ route('tires.show', [$tire, 'edit' => 1]) }}" class="btn btn-dark">Editar</a>
            @endif
        @endif
    </x-slot:actions>
</x-page-header>

@if($repaired)
    <div class="flash flash--ok" role="status">Esta cubierta tiene una <strong>reparación (parche)</strong>. No abre vida nueva: se puede devolver a stock e instalar de nuevo.</div>
@endif

<div class="grid lg:grid-cols-3 gap-5">
    <x-panel title="Ficha">
        <div class="dl">
            <div><span>Estado</span><x-status :tone="$tire->status->tone()">{{ $tire->status->label() }}</x-status></div>
            <div><span>Condición</span><x-status :tone="$tire->condition->tone()">{{ $tire->condition->label() }}</x-status></div>
            <div><span>Vida actual</span>{{ $tire->currentLifecycle?->life_number ?? 1 }} de {{ $tire->lifecycles->count() ?: 1 }}</div>
            <div><span>Km acumulados</span><span class="mono">{{ number_format($tire->accumulated_km) }}</span></div>
            @php $costTotal = $tire->costEntries->sum('amount'); @endphp
            <div><span>Costo acumulado</span><span class="mono">{{ $costTotal ? '$ '.number_format($costTotal, 2, ',', '.') : '—' }}</span></div>
            <div><span>$ / km</span><span class="mono">{{ ($costTotal && $tire->accumulated_km) ? number_format($costTotal / $tire->accumulated_km, 4, ',', '.') : '—' }}</span></div>
            @if($tire->costEntries->isNotEmpty())
                <div class="mt-3 col-span-full">
                    <h3 class="font-semibold mb-2 text-sm">Serie de precios / costos</h3>
                    <ul class="text-sm space-y-1">
                        @foreach($tire->costEntries->sortBy('occurred_at') as $entry)
                            <li>
                                <span class="mono">{{ $entry->occurred_at->format('d/m/Y') }}</span>
                                · {{ $entry->categoryLabel() }}
                                · <span class="mono">$ {{ number_format($entry->amount, 2, ',', '.') }}</span>
                                @if($entry->unit_price !== null)
                                    <span class="hint">(p.u. $ {{ number_format($entry->unit_price, 2, ',', '.') }})</span>
                                @endif
                                @if($entry->fleetUnit || $entry->unitPosition)
                                    <span class="hint">
                                        — {{ $entry->fleetUnit?->plate }}
                                        {{ $entry->unitPosition?->name }}
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div><span>Profundidad mín.</span>{{ $tire->current_tread_min ? $tire->current_tread_min.' mm' : '—' }}</div>
            <div>
                <span>Ubicación</span>
                @if($loc?->unit)
                    <a href="{{ route('units.show', $loc->unit) }}">{{ $loc->unit->plate }}</a>
                    · {{ $loc->position?->name }}
                @else
                    {{ $loc?->location_kind?->label() ?? '—' }}
                    @if($loc?->base) · {{ $loc->base->name }} @endif
                @endif
            </div>
            <div><span>Alta</span>{{ $tire->purchased_at?->format('d/m/Y') ?? '—' }}</div>
            @if($tire->public_token)
                <div>
                    <span>QR</span>
                    <a href="{{ route('qr.resolve', $tire->public_token) }}">
                        <img src="{{ route('tires.qr', $tire) }}" alt="QR de {{ $tire->displayName() }}" width="96" height="96">
                    </a>
                </div>
            @endif
            <div><span>Última intervención</span>@if($lastEvent){{ $lastEvent['at']?->format('d/m/Y H:i') ?? '—' }}{{ !empty($lastEvent['headline']) ? ' · '.$lastEvent['headline'] : '' }}@else—@endif</div>
        </div>
        @if($lastMeasure)
            <div class="mt-4">
                <h3 class="font-semibold mb-2">Última profundidad</h3>
                <ul class="text-sm space-y-1">
                    @foreach($lastMeasure->readings as $reading)
                        <li>{{ $reading->zone?->name ?? 'Zona' }}: <span class="mono">{{ $reading->millimeters }} mm</span></li>
                    @endforeach
                </ul>
                @if($lastMeasure->raises_alert)
                    <p class="hint mt-2">Alerta de desgaste irregular.</p>
                @endif
            </div>
        @endif
        @if($canReturn)
            <form method="POST" action="{{ route('tires.return-stock', $tire) }}" class="mt-6 space-y-3 border-t border-slate-100 pt-4" data-confirm="La cubierta vuelve a stock. El parche queda en la ficha y no se abre una vida nueva. ¿Continuar?">
                @csrf
                <h3 class="font-semibold">Volver a stock</h3>
                <p class="hint">Desde stock se puede reinstalar. Un parche no es recapado.</p>
                <label class="field"><span>Observaciones</span><textarea name="notes" rows="2" placeholder="Parche interno, queda lista"></textarea></label>
                <button class="btn btn-dark btn-sm">Devolver a stock</button>
            </form>
        @endif
        @if(auth()->user()->role->canManageAbm() && request('edit'))
            <form method="POST" action="{{ route('tires.update', $tire) }}" class="mt-6 space-y-3 border-t border-slate-100 pt-4">
                @csrf
                @method('PUT')
                <h3 class="font-semibold">Editar ficha</h3>
                <label class="field"><span>Nº individual</span><input name="individual_number" type="number" min="1" value="{{ old('individual_number', $tire->individual_number) }}" required @error('individual_number') aria-invalid="true" @enderror><x-field-error name="individual_number" /></label>
                <label class="field"><span>Motivo del cambio de número</span><input name="number_reason" value="{{ old('number_reason') }}" placeholder="Obligatorio si cambiás el número"><x-field-error name="number_reason" /></label>
                <label class="field"><span>Marca</span>
                    <select name="tire_brand_id" required>
                        @foreach($brands as $brand)
                            <option value="{{ $brand->id }}" @selected($tire->tire_brand_id === $brand->id)>{{ $brand->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field"><span>Modelo</span>
                    <select name="tire_model_id" required>
                        @foreach($models as $model)
                            <option value="{{ $model->id }}" @selected($tire->tire_model_id === $model->id)>{{ $model->brand?->name }} {{ $model->code }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field"><span>Medida</span>
                    <select name="tire_size_id" required>
                        @foreach($sizes as $size)
                            <option value="{{ $size->id }}" @selected($tire->tire_size_id === $size->id)>{{ $size->displayName() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field"><span>Condición</span>
                    <select name="condition" required>
                        @foreach($conditions as $condition)
                            <option value="{{ $condition->value }}" @selected($tire->condition === $condition)>{{ $condition->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="btn btn-dark btn-sm">Guardar</button>
            </form>
        @endif
    </x-panel>

    @if(auth()->user()->role->canWrite() && $tire->status->value !== 'DE_BAJA')
    <x-panel title="Nueva incidencia">
        <form method="POST" action="{{ route('tires.incidents.store', $tire) }}" class="space-y-3">
            @csrf
            <label class="field"><span>Tipo</span>
                <select name="type">
                    @foreach($incidentTypes as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field"><span>Descripción</span><input name="description"></label>
            <label class="field"><span>Observaciones</span><textarea name="notes" rows="3"></textarea></label>
            <button class="btn btn-dark">Registrar</button>
        </form>
        <p class="hint mt-3">Reparación o parche marcan la cubierta como reparada. Recapado (si tu rol lo permite) abre una vida nueva.</p>
    </x-panel>
    @endif

    <x-panel title="Medición de profundidad">
        @if(auth()->user()->role->canWrite() && $tire->status->value !== 'DE_BAJA')
        <form method="POST" action="{{ route('tires.measurements.store', $tire) }}" class="space-y-3">
            @csrf
            @foreach($tire->size->zones as $i => $zone)
                <label class="field">
                    <span>{{ $zone->name }} (mm)</span>
                    <input type="hidden" name="readings[{{ $i }}][zone_id]" value="{{ $zone->id }}">
                    <input name="readings[{{ $i }}][millimeters]" type="number" step="0.1" min="0" required>
                </label>
            @endforeach
            <button class="btn btn-dark">Guardar medición</button>
        </form>
        @endif
        @if(auth()->user()->role->canRetireOrRecap() && $tire->status->value !== 'DE_BAJA')
            <form method="POST" action="{{ route('tires.retire', $tire) }}" class="mt-6 space-y-3 border-t border-slate-100 pt-4" data-confirm="La baja es definitiva e irreversible. El historial se conserva, pero esta cubierta no se puede reinstalar. ¿Dar de baja?">
                @csrf
                <h3 class="font-semibold">Baja definitiva</h3>
                <p class="hint">La cubierta sale de circulación. No vuelve a stock ni a una unidad. El historial queda en la ficha.</p>
                <label class="field"><span>Motivo</span>
                    <select name="reason_id" required>
                        @foreach($retirementReasons as $reason)
                            <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field"><span>Observaciones</span><textarea name="notes" rows="2" placeholder="Contexto obligatorio"></textarea></label>
                <button class="btn btn-danger">Dar de baja</button>
            </form>
        @endif
    </x-panel>
</div>

@if(($numberChanges ?? collect())->isNotEmpty())
<x-panel title="Cambios de número individual" class="mt-6">
    <x-content-table :small="true">
        <thead><tr><th>De</th><th>A</th><th>Quién</th><th>Cuándo</th><th>Motivo</th></tr></thead>
        <tbody>
        @foreach($numberChanges as $change)
            <tr>
                <td class="mono">{{ $change->from_number }}</td>
                <td class="mono">{{ $change->to_number }}</td>
                <td>{{ $change->user?->name }}</td>
                <td class="mono">{{ $change->created_at->format('d/m/Y H:i') }}</td>
                <td>{{ $change->reason }}</td>
            </tr>
        @endforeach
        </tbody>
    </x-content-table>
</x-panel>
@endif

<x-panel title="Vidas" class="mt-6">
    <x-content-table :small="true">
        <thead><tr><th>Vida</th><th>Inicio</th><th>Cierre</th><th>Km</th><th>Origen</th></tr></thead>
        <tbody>
        @forelse($tire->lifecycles->sortBy('life_number') as $life)
            <tr>
                <td class="mono">{{ $life->life_number }}</td>
                <td class="mono">{{ $life->started_at?->format('d/m/Y') }}</td>
                <td class="mono">{{ $life->ended_at?->format('d/m/Y') ?? 'Abierta' }}</td>
                <td class="mono">{{ number_format($life->km_in_life) }}</td>
                <td>{{ $life->started_by }}</td>
            </tr>
        @empty
            <tr><td colspan="5"><x-empty title="Sin vidas registradas" /></td></tr>
        @endforelse
        </tbody>
    </x-content-table>
</x-panel>

<x-panel title="Historial" class="mt-6">
    @if($timeline->isEmpty())
        <x-empty title="Todavía no hay eventos" text="Cuando se monte, mida o repare, el historial aparece acá en una sola línea de tiempo." />
    @else
        <p class="timeline__legend">
            <span><i class="tl-dot tl-dot--green"></i> Alta / montaje</span>
            <span><i class="tl-dot tl-dot--orange"></i> Incidencia</span>
            <span><i class="tl-dot tl-dot--amber"></i> Ubicación</span>
            <span><i class="tl-dot tl-dot--blue"></i> Vida</span>
            <span><i class="tl-dot tl-dot--red"></i> Baja / alerta</span>
        </p>
        <ol class="timeline">
            @foreach($timeline as $item)
                <li class="tl-card tl-card--{{ $item['tone'] }}">
                    <div class="tl-card__rail" aria-hidden="true"><i></i></div>
                    <div class="tl-card__body">
                        <div class="tl-card__meta">
                            <span class="tl-card__kind">{{ $item['kind_label'] }}</span>
                            <span>{{ $item['at']?->format('d/m/Y H:i') ?? '—' }}@if(!empty($item['user'])) · {{ $item['user'] }}@endif</span>
                        </div>
                        <h3 class="tl-card__title">{{ $item['headline'] }}</h3>
                        @if(!empty($item['summary']))
                            <p class="tl-card__summary">{{ $item['summary'] }}</p>
                        @endif
                        @if($item['steps']->isNotEmpty())
                            <ol class="tl-steps">
                                @foreach($item['steps'] as $step)
                                    <li>
                                        <span>{{ $step['kind_label'] }}</span>
                                        {{ $step['title'] }}@if(!empty($step['body'])) — {{ $step['body'] }}@endif
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</x-panel>
@endsection
