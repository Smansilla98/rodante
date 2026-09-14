@extends('layouts.app')
@section('kicker', 'Operación')
@section('title', 'Dar de baja')
@section('content')
<x-page-header
    kicker="Operación"
    title="Dar de baja"
    subtitle="Solo cubiertas que no estén colocadas en una unidad. Si está montada o en auxilio, primero retiralas a stock desde la planilla."
>
    <x-slot:actions>
        <a href="{{ route('tires.index', ['status' => 'DE_BAJA']) }}" class="btn btn-ghost">Ver ya dadas de baja</a>
        <a href="{{ route('tires.index', ['queue' => 'retirement']) }}" class="btn btn-ghost">Cola próximas a baja</a>
    </x-slot:actions>
</x-page-header>

@if($errors->has('retire'))
    <div class="flash flash--bad" role="alert">{{ $errors->first('retire') }}</div>
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
    <p class="hint mb-4">Están fuera de unidad (stock, reserva o reparación). Tocá una fila para cargar el motivo y confirmar.</p>

    <div class="action-cards">
        @forelse($eligible as $tire)
            <details class="action-card action-card--danger" @if((string) old('tire_focus') === (string) $tire->id) open @endif>
                <summary class="action-card__summary">
                    <span class="action-card__title">{{ $tire->displayName() }}</span>
                    <span class="action-card__desc">
                        {{ $tire->brand?->name }} · {{ $tire->size?->displayName() }}
                        · {{ $tire->status->label() }}
                        · {{ number_format($tire->accumulated_km) }} km
                        · {{ $tire->currentLocation?->base?->name ?? 'Sin base' }}
                    </span>
                </summary>
                <div class="action-card__body">
                    <form method="POST" action="{{ route('retirements.store', $tire) }}" enctype="multipart/form-data" class="space-y-3" data-confirm="La baja es definitiva. Esta cubierta no se podrá reinstalar. ¿Continuar?">
                        @csrf
                        <input type="hidden" name="tire_focus" value="{{ $tire->id }}">
                        <label class="field">
                            <span>Motivo</span>
                            <select name="reason_id" required>
                                <option value="">Elegí un motivo…</option>
                                @foreach($reasons as $reason)
                                    <option value="{{ $reason->id }}" @selected((string) old('reason_id') === (string) $reason->id)>{{ $reason->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field">
                            <span>Observaciones</span>
                            <textarea name="notes" rows="2" placeholder="Ej. Fin de vida útil, carcasa agrietada" required>{{ old('notes') }}</textarea>
                        </label>
                        <label class="field">
                            <span>Fotos (opcional)</span>
                            <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" capture="environment" multiple data-photo-input="#retirePreview{{ $tire->id }}">
                        </label>
                        <div id="retirePreview{{ $tire->id }}" class="photo-grid" aria-live="polite"></div>
                        <p class="hint">Hasta 6 fotos. En el celular se puede abrir la cámara.</p>
                        <a href="{{ route('tires.show', $tire) }}" class="btn btn-ghost btn-sm">Ver ficha</a>
                        <button class="btn btn-danger action-card__btn">Confirmar baja</button>
                    </form>
                </div>
            </details>
        @empty
            <x-panel>
                <x-empty title="No hay cubiertas elegibles" text="Si la que buscás está en una unidad, aparece abajo. Primero retiralas a stock desde la planilla." />
            </x-panel>
        @endforelse
    </div>
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
