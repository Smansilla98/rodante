@extends('layouts.app')
@section('kicker', 'Planilla')
@section('title', $sheetUnits->pluck('plate')->join(' + '))
@section('content')
@php $coupled = $unit->coupledPartner(); @endphp

<x-page-header
    kicker="Planilla"
    :title="$sheetUnits->pluck('plate')->join(' + ')"
    :subtitle="$unit->type->name.' · '.$unit->configuration->name.' · '.$unit->fleet->name.' · códigos TC/SR por eje-lado'"
>
    <x-slot:actions>
        <a href="{{ route('units.index') }}" class="btn btn-ghost"><x-icon name="back" class="w-4 h-4" /> Unidades</a>
    </x-slot:actions>
</x-page-header>

<div class="mb-8">
    <x-tire-sheet :units="$sheetUnits" :current="$unit" />
</div>

<div class="grid lg:grid-cols-3 gap-5 mb-6 no-print">
    <x-panel title="Datos">
        <div class="dl">
            <div><span>Base</span>{{ $unit->base->name }}</div>
            <div><span>Equipo</span>{{ $unit->brand }} {{ $unit->model_name }}</div>
            <div><span>Odómetro</span>{{ $unit->hasOdometer() ? number_format($unit->current_odometer).' km' : 'Usa el del tractor acoplado' }}</div>
            <div><span>Acoplado</span>{{ $coupled?->plate ?? 'Sin acoplar' }}</div>
        </div>
    </x-panel>

    <x-panel title="Acoplar / desacoplar">
        <form method="POST" action="{{ route('units.couple', $unit) }}" class="flex flex-wrap gap-2 mb-3">
            @csrf
            <select name="other_unit_id" class="inp flex-1 min-w-32">
                @foreach(($unit->hasOdometer() ? $trailers : $tractors) as $other)
                    <option value="{{ $other->id }}">{{ $other->plate }}</option>
                @endforeach
            </select>
            <input name="odometer" type="number" class="inp w-28" placeholder="Km" required>
            <button class="btn btn-dark btn-sm">Acoplar</button>
        </form>
        @if($coupled)
            <form method="POST" action="{{ route('units.uncouple', $unit) }}" class="flex gap-2">
                @csrf
                <input name="odometer" type="number" class="inp w-28" placeholder="Km" required>
                <button class="btn btn-ghost btn-sm">Desacoplar</button>
            </form>
        @endif
    </x-panel>

    @if(auth()->user()->role->canChangeConfiguration())
        <x-panel title="Cambio de configuración">
            <form method="POST" action="{{ route('units.configuration', $unit) }}" class="space-y-3">
                @csrf
                <select name="unit_configuration_id" class="inp">
                    @foreach($configurations as $cfg)
                        <option value="{{ $cfg->id }}" @selected($cfg->id===$unit->unit_configuration_id)>{{ $cfg->code }}</option>
                    @endforeach
                </select>
                <input name="reason" class="inp" placeholder="Motivo (urbana → ripio)" required>
                <button class="btn btn-ghost btn-sm">Cambiar</button>
            </form>
        </x-panel>
    @endif
</div>

<form method="POST" action="{{ route('units.operate', $unit) }}" class="no-print mb-6" id="operation-form">
    @csrf
    <x-panel title="Carga / descarga">
        <p class="text-sm text-slate-500 mb-4">Un retiro siempre pasa por STOCK, aunque después se instale en otra posición o unidad.</p>
        <div class="grid md:grid-cols-2 gap-4 mb-6">
            <label class="field"><span>Odómetro del tractor</span>
                <input name="odometer" type="number" required value="{{ $unit->hasOdometer() ? $unit->current_odometer : ($unit->currentCouplingAsTrailer?->tractor?->current_odometer ?? '') }}">
            </label>
            <label class="field"><span>Notas</span><input name="notes"></label>
        </div>

        <h3 class="font-semibold mb-2">Retirar</h3>
        <div class="space-y-2 mb-6">
            @forelse($layout->where(fn($s) => $s['tire']) as $i => $slot)
                <label class="flex flex-wrap items-center gap-3 text-sm py-2 border-b border-slate-100">
                    <input type="checkbox" name="removals[{{ $i }}][tire_id]" value="{{ $slot['tire']->id }}">
                    <span class="min-w-48">{{ $slot['position']->name }} — {{ $slot['tire']->displayName() }}</span>
                    <select name="removals[{{ $i }}][reason_id]" class="inp" style="width:auto;min-width:140px">
                        <option value="">Motivo</option>
                        @foreach($reasons as $reason)<option value="{{ $reason->id }}">{{ $reason->name }}</option>@endforeach
                    </select>
                    <select name="removals[{{ $i }}][destination]" class="inp" style="width:auto">
                        @foreach($destinations as $dest)<option value="{{ $dest->value }}">{{ $dest->label() }}</option>@endforeach
                    </select>
                </label>
            @empty
                <p class="text-sm text-slate-500">No hay cubiertas instaladas para retirar.</p>
            @endforelse
        </div>

        <h3 class="font-semibold mb-2">Instalar desde stock</h3>
        <div class="space-y-2 mb-5">
            @foreach($unit->configuration->positions as $i => $position)
                <div class="grid md:grid-cols-2 gap-2 items-center text-sm">
                    <div>{{ $position->name }}</div>
                    <select name="installations[{{ $i }}][tire_id]" class="inp">
                        <option value="">—</option>
                        @foreach($stockTires as $tire)
                            <option value="{{ $tire->id }}">{{ $tire->displayName() }} · {{ $tire->size->code }}</option>
                        @endforeach
                    </select>
                    <input type="hidden" name="installations[{{ $i }}][position_id]" value="{{ $position->id }}">
                </div>
            @endforeach
        </div>
        <button class="btn btn-primary">Confirmar operación</button>
    </x-panel>
</form>

<form method="POST" action="{{ route('units.rotate', $unit) }}" class="no-print mb-6">
    @csrf
    <x-panel title="Rotación" >
        <p class="text-sm text-slate-500 mb-4">No cierra kilómetros: la cubierta sigue en la misma vida.</p>
        <div class="grid md:grid-cols-3 gap-3">
            <select name="tire_id" class="inp" required>
                <option value="">Neumático</option>
                @foreach($layout->where(fn($s) => $s['tire']) as $slot)
                    <option value="{{ $slot['tire']->id }}">{{ $slot['tire']->displayName() }}</option>
                @endforeach
            </select>
            <select name="position_id" class="inp" required>
                @foreach($unit->configuration->positions as $position)
                    <option value="{{ $position->id }}">{{ $position->name }}</option>
                @endforeach
            </select>
            <input name="odometer" type="number" class="inp" placeholder="Odómetro" required>
        </div>
        <button class="btn btn-dark mt-4">Rotar</button>
    </x-panel>
</form>

<x-panel title="Historial en esta patente" :flush="true" class="no-print">
    <div class="tbl-wrap">
        <table class="tbl">
            <thead><tr><th>Fecha</th><th>Evento</th><th>Cubierta</th><th>Detalle</th></tr></thead>
            <tbody>
            @forelse($history as $movement)
                <tr>
                    <td class="mono">{{ $movement->occurred_at?->format('d/m/Y H:i') }}</td>
                    <td>{{ $movement->type->label() }}</td>
                    <td><a href="{{ route('tires.show', $movement->tire) }}">{{ $movement->tire->displayName() }}</a></td>
                    <td>{{ $movement->fromPosition?->name }} {{ $movement->toPosition?->name }} @if($movement->km_delta)+{{ number_format($movement->km_delta) }} km @endif</td>
                </tr>
            @empty
                <tr><td colspan="4"><x-empty title="Sin movimientos en esta unidad" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-panel>
@endsection
