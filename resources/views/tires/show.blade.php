@extends('layouts.app')
@section('kicker', 'Cubierta')
@section('title', $tire->displayName())
@section('content')
<x-page-header kicker="Cubierta" :title="$tire->displayName()" :subtitle="$tire->fullName().' · '.$tire->size->displayName()">
    <x-slot:actions>
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

<div class="grid lg:grid-cols-3 gap-5">
    <x-panel title="Ficha">
        <div class="dl">
            <div><span>Estado</span><x-status :tone="$tire->status->tone()">{{ $tire->status->label() }}</x-status></div>
            <div><span>Condición</span>{{ $tire->condition->label() }}</div>
            <div><span>Vida actual</span>{{ $tire->currentLifecycle?->life_number ?? 1 }}</div>
            <div><span>Km acumulados</span><span class="mono">{{ number_format($tire->accumulated_km) }}</span></div>
            <div><span>Profundidad mín.</span>{{ $tire->current_tread_min ? $tire->current_tread_min.' mm' : '—' }}</div>
            <div>
                <span>Ubicación</span>
                @if($tire->currentLocation?->unit)
                    <a href="{{ route('units.show', $tire->currentLocation->unit) }}">{{ $tire->currentLocation->unit->plate }}</a>
                    · {{ $tire->currentLocation->position?->name }}
                @else
                    {{ $tire->currentLocation?->location_kind->value ?? '—' }}
                    {{ $tire->currentLocation?->base?->name }}
                @endif
            </div>
        </div>
        @if(auth()->user()->role->canManageAbm() && request('edit'))
            <form method="POST" action="{{ route('tires.update', $tire) }}" class="mt-6 space-y-3 border-t border-slate-100 pt-4">
                @csrf
                @method('PUT')
                <h3 class="font-semibold">Editar ficha</h3>
                <label class="field"><span>Nº individual</span><input name="individual_number" type="number" min="1" value="{{ $tire->individual_number }}" required></label>
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

    @if(auth()->user()->role->canWrite())
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
    </x-panel>
    @endif

    <x-panel title="Medición de profundidad">
        @if(auth()->user()->role->canWrite())
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
            <form method="POST" action="{{ route('tires.retire', $tire) }}" class="mt-6 space-y-3 border-t border-slate-100 pt-4">
                @csrf
                <h3 class="font-semibold">Baja definitiva</h3>
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

<x-panel title="Línea de tiempo" class="mt-6">
    <ol class="space-y-4">
        @foreach($tire->movements as $movement)
            <li class="border-l-2 border-amber-500 pl-4">
                <div class="text-xs text-slate-500">{{ $movement->occurred_at?->format('d/m/Y H:i') }} · {{ $movement->type->label() }}</div>
                <div class="text-sm">
                    @if($movement->fromUnit) {{ $movement->fromUnit->plate }} {{ $movement->fromPosition?->name }} @endif
                    @if($movement->toUnit) → {{ $movement->toUnit->plate }} {{ $movement->toPosition?->name }} @endif
                    @if($movement->km_delta) · +{{ number_format($movement->km_delta) }} km @endif
                </div>
            </li>
        @endforeach
        @foreach($tire->incidents as $incident)
            <li class="border-l-2 border-slate-300 pl-4">
                <div class="text-xs text-slate-500">{{ $incident->occurred_at->format('d/m/Y H:i') }} · {{ $incident->type->label() }}</div>
                <div class="text-sm">{{ $incident->description }}</div>
            </li>
        @endforeach
    </ol>
</x-panel>
@endsection
