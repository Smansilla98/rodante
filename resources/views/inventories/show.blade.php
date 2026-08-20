@extends('layouts.app')
@section('title', $session->number)
@section('content')
<x-page-header kicker="Depósito" :title="$session->number" :subtitle="$session->base?->name.' · '.$session->status->label()">
    <x-slot:actions>
        <a class="btn" href="{{ route('inventories.index') }}">Listado</a>
    </x-slot:actions>
</x-page-header>

@if($errors->any())
    <div class="flash flash--err" role="alert">{{ $errors->first() }}</div>
@endif

<div class="grid lg:grid-cols-3 gap-5 mb-5">
    <x-panel title="Resumen">
        <div class="dl">
            <div><span>Estado</span>{{ $session->status->label() }}</div>
            <div><span>Esperadas</span><span class="mono">{{ $session->expected_count }}</span></div>
            <div><span>Contadas</span><span class="mono">{{ $session->found_count }}</span></div>
            <div><span>Faltantes</span><span class="mono">{{ $session->missing_count }}</span></div>
            <div><span>Sobrantes / otras</span><span class="mono">{{ $session->unexpected_count }}</span></div>
            <div><span>Abierta por</span>{{ $session->opener?->name }}</div>
            @if($session->closed_at)
                <div><span>Cerrada</span>{{ $session->closed_at->format('d/m/Y H:i') }}</div>
                <div><span>Ajustes</span>{{ $session->adjustments_applied ? 'Sí (bases)' : 'No' }}</div>
            @endif
        </div>
    </x-panel>

    <x-panel title="Acciones" class="lg:col-span-2">
        @if($session->status->value === 'OPEN' && auth()->user()->can('count', $session))
            <form method="POST" action="{{ route('inventories.start', $session) }}" class="mb-4">
                @csrf
                <p class="hint mb-3">El snapshot ya está tomado. Iniciá el conteo para escanear.</p>
                <button class="btn btn-dark">Iniciar conteo</button>
            </form>
        @endif

        @if($session->status->canScan() && auth()->user()->can('count', $session))
            <form method="POST" action="{{ route('inventories.scan', $session) }}" class="space-y-3 mb-4" data-loading>
                @csrf
                <label class="field">
                    <span>Número individual o token QR</span>
                    <input name="q" value="{{ old('q') }}" autofocus inputmode="numeric" placeholder="Ej. 30363" required>
                    <x-field-error name="q" />
                </label>
                <button class="btn btn-dark">Registrar conteo</button>
            </form>
            <form method="POST" action="{{ route('inventories.review', $session) }}" data-confirm="Las no contadas quedarán como faltantes. ¿Enviar a revisión?">
                @csrf
                <button class="btn">Terminar conteo → revisión</button>
            </form>
        @endif

        @if($session->status->value === 'REVIEW' && auth()->user()->can('close', $session))
            <div class="space-y-4">
                <p class="hint">Revisá la tabla. Cerrar sin ajustes solo deja auditoría. Aplicar correcciones mueve cubiertas stockables a esta base (nunca desmonta ni da de baja).</p>
                <form method="POST" action="{{ route('inventories.close', $session) }}" class="space-y-3">
                    @csrf
                    <label class="field"><span>Notas de cierre</span><textarea name="notes" rows="2"></textarea></label>
                    <div class="flex flex-wrap gap-2">
                        <button class="btn btn-dark" name="apply_fixes" value="0">Cerrar sin mover</button>
                        @can('adjust', $session)
                            <button class="btn" name="apply_fixes" value="1" data-confirm="Se corregirá la base de sobrantes/wrong_base stockables. ¿Continuar?">Cerrar y corregir bases</button>
                        @endcan
                    </div>
                </form>
            </div>
        @endif

        @if($session->status->isActive() && auth()->user()->can('cancel', $session))
            <form method="POST" action="{{ route('inventories.cancel', $session) }}" class="mt-6 border-t border-slate-100 pt-4" data-confirm="¿Cancelar este inventario?">
                @csrf
                <label class="field"><span>Motivo</span><input name="notes" placeholder="Opcional"></label>
                <button class="btn btn-sm mt-2">Cancelar inventario</button>
            </form>
        @endif
    </x-panel>
</div>

<x-panel title="Líneas" :flush="true">
    <x-content-table>
        <thead>
            <tr>
                <th>Cubierta</th>
                <th>Esperado</th>
                <th>Resultado</th>
                <th>Escaneo</th>
            </tr>
        </thead>
        <tbody>
        @forelse($lines as $line)
            <tr>
                <td>
                    <a href="{{ route('tires.show', $line->tire) }}">{{ $line->tire?->displayName() }}</a>
                    <div class="hint mono">Nº {{ $line->tire?->individual_number }}</div>
                </td>
                <td>
                    @if($line->in_snapshot)
                        {{ $line->expected_kind?->label() ?? '—' }}
                        @if($line->expectedBase) · {{ $line->expectedBase->name }} @endif
                    @else
                        <span class="hint">No estaba en el snapshot</span>
                    @endif
                </td>
                <td>
                    @if($line->delta)
                        <x-status :tone="$line->delta->tone()">{{ $line->delta->label() }}</x-status>
                    @elseif($line->found)
                        Contada
                    @else
                        Pendiente
                    @endif
                    @if($line->adjustment_applied)
                        <div class="hint">Ajuste aplicado</div>
                    @endif
                    @if($line->notes)
                        <div class="hint">{{ $line->notes }}</div>
                    @endif
                </td>
                <td class="mono">
                    @if($line->scanned_at)
                        {{ $line->scanned_at->format('d/m H:i') }}
                        <div class="hint">{{ $line->scanner?->name }}</div>
                    @else
                        —
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="4"><x-empty title="Sin líneas" text="El snapshot de la base estaba vacío o aún no se abrió." /></td></tr>
        @endforelse
        </tbody>
    </x-content-table>
    <div class="pager">{{ $lines->links() }}</div>
</x-panel>
@endsection
