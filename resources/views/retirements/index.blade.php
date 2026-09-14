@extends('layouts.app')
@section('kicker', 'Operación')
@section('title', 'Dar de baja')
@section('content')
<x-page-header
    kicker="Operación"
    title="Dar de baja"
    subtitle="Marcá las cubiertas fuera de unidad y dales de baja juntas. Si están montadas, primero retiralas a stock desde la planilla."
>
    <x-slot:actions>
        <a href="{{ route('tires.index', ['status' => 'DE_BAJA']) }}" class="btn btn-ghost">Ver ya dadas de baja</a>
        <a href="{{ route('tires.index', ['queue' => 'retirement']) }}" class="btn btn-ghost">Cola próximas a baja</a>
    </x-slot:actions>
</x-page-header>

@if($errors->has('retire'))
    <div class="flash flash--bad" role="alert">{{ $errors->first('retire') }}</div>
@endif
@if($errors->has('tire_ids'))
    <div class="flash flash--bad" role="alert">{{ $errors->first('tire_ids') }}</div>
@endif

<form class="toolbar mb-5" method="GET" action="{{ route('retirements.index') }}">
    <input name="q" value="{{ $q }}" placeholder="Nº de cubierta, modelo o patente" aria-label="Buscar">
    <button class="btn btn-dark btn-sm">Buscar</button>
    @if($q !== '')
        <a href="{{ route('retirements.index') }}" class="btn btn-ghost btn-sm">Limpiar</a>
    @endif
</form>

<section class="mb-8" aria-labelledby="eligibleTitle">
    <h2 id="eligibleTitle" class="text-lg font-bold mb-2">Listas para dar de baja</h2>
    <p class="hint mb-4">Marcá una o varias, elegí el motivo y confirmá. La baja es definitiva.</p>

    <form method="POST" action="{{ route('retirements.bulk') }}" data-confirm="La baja es definitiva. Las cubiertas marcadas no se podrán reinstalar. ¿Continuar?">
        @csrf
        <x-panel :flush="true">
            <x-content-table>
                <thead>
                    <tr>
                        <th scope="col" class="w-10">
                            <input type="checkbox" data-check-all="retire-row" aria-label="Marcar todas en esta página" @disabled($eligible->isEmpty())>
                        </th>
                        <th scope="col">Cubierta</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Km</th>
                        <th scope="col">Ubicación</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($eligible as $tire)
                    <tr>
                        <td>
                            <input
                                type="checkbox"
                                class="retire-row"
                                name="tire_ids[]"
                                value="{{ $tire->id }}"
                                @checked(collect(old('tire_ids', []))->map(fn ($id) => (string) $id)->contains((string) $tire->id))
                                aria-label="Seleccionar {{ $tire->displayName() }}"
                            >
                        </td>
                        <td>
                            <a href="{{ route('tires.show', $tire) }}">{{ $tire->displayName() }}</a>
                            <div class="text-xs text-slate-500">{{ $tire->brand?->name }} · {{ $tire->size?->displayName() }}</div>
                        </td>
                        <td><x-status :tone="$tire->status->tone()">{{ $tire->status->label() }}</x-status></td>
                        <td class="mono">{{ number_format($tire->accumulated_km) }}</td>
                        <td>{{ $tire->currentLocation?->base?->name ?? 'Sin base' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty title="No hay cubiertas elegibles" text="Si la que buscás está en una unidad, aparece abajo. Primero retiralas a stock desde la planilla." /></td></tr>
                @endforelse
                </tbody>
            </x-content-table>
        </x-panel>

        @if($eligible->isNotEmpty())
            <div class="toolbar mt-4 flex-wrap items-end gap-3">
                <label class="field">
                    <span>Motivo</span>
                    <select name="reason_id" required>
                        <option value="">Elegí un motivo…</option>
                        @foreach($reasons as $reason)
                            <option value="{{ $reason->id }}" @selected((string) old('reason_id') === (string) $reason->id)>{{ $reason->name }}</option>
                        @endforeach
                    </select>
                    <x-field-error name="reason_id" />
                </label>
                <label class="field" style="min-width:16rem;flex:1">
                    <span>Observaciones</span>
                    <input type="text" name="notes" value="{{ old('notes') }}" placeholder="Opcional. Ej. Fin de vida útil" maxlength="500">
                </label>
                <button class="btn btn-danger" type="submit">Dar de baja seleccionadas</button>
            </div>
        @endif
    </form>
    <div class="pager mt-4">{{ $eligible->links() }}</div>
</section>

<section aria-labelledby="blockedTitle">
    <h2 id="blockedTitle" class="text-lg font-bold mb-2">No se pueden dar de baja (siguen en una unidad)</h2>
    <p class="hint mb-4">Hay que retirarlas a stock desde la planilla. Después vuelven a la lista de arriba.</p>

    <x-panel :flush="true">
        <x-content-table>
            <thead>
                <tr>
                    <th scope="col">Cubierta</th>
                    <th scope="col">Estado</th>
                    <th scope="col">Dónde está</th>
                    <th scope="col">Qué hacer</th>
                </tr>
            </thead>
            <tbody>
            @forelse($blocked as $tire)
                <tr>
                    <td>
                        <a href="{{ route('tires.show', $tire) }}">{{ $tire->displayName() }}</a>
                        <div class="text-xs text-slate-500">{{ $tire->brand?->name }} · {{ number_format($tire->accumulated_km) }} km</div>
                    </td>
                    <td><x-status :tone="$tire->status->tone()">{{ $tire->status->label() }}</x-status></td>
                    <td>
                        @if($tire->currentLocation?->unit)
                            {{ $tire->currentLocation->unit->plate }}
                            @if($tire->currentLocation->position)
                                · {{ $tire->currentLocation->position->name }}
                            @endif
                        @elseif($tire->openAssignment?->unit)
                            {{ $tire->openAssignment->unit->plate }}
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if($tire->currentLocation?->unit)
                            <a class="btn btn-dark btn-sm" href="{{ route('units.show', $tire->currentLocation->unit) }}">Abrir planilla y retirar</a>
                        @elseif($tire->openAssignment?->unit)
                            <a class="btn btn-dark btn-sm" href="{{ route('units.show', $tire->openAssignment->unit) }}">Abrir planilla y retirar</a>
                        @else
                            <span class="hint">Revisá la ficha</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4"><x-empty title="Ninguna cubierta bloqueada" text="Todas las de la búsqueda están fuera de unidad o no hay resultados." /></td></tr>
            @endforelse
            </tbody>
        </x-content-table>
        <div class="pager">{{ $blocked->links() }}</div>
    </x-panel>
</section>
@endsection
