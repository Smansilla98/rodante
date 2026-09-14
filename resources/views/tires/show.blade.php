@extends('layouts.app')
@section('kicker', 'Cubierta')
@section('title', $tire->displayName())
@section('content')
@php
    $user = auth()->user();
    $loc = $tire->currentLocation;
    $lastMeasure = $tire->measurements->sortByDesc('measured_at')->first();
    $timelineNewest = $timeline->reverse()->values();
    $lastEvent = $timelineNewest->first();
    $status = $tire->status->value;
    $canWrite = $user->role->canWrite();
    $canRetire = $user->role->canRetireOrRecap();
    $canReturn = $canWrite && in_array($status, ['EN_REPARACION', 'RESERVA'], true);
    $canOperate = $canWrite && $status !== 'DE_BAJA';
    $canTransfer = $canWrite && $status === 'STOCK' && isset($bases) && $bases->isNotEmpty();
    $repaired = $tire->condition->value === 'REPARADA';
    $forecast = $forecast ?? [];
    $costTotal = $tire->costEntries->sum('amount');
    $openMeasure = $errors->hasAny(['readings']) || collect($errors->keys())->contains(fn ($k) => str_starts_with((string) $k, 'readings.'));
    $openIncident = $errors->hasAny(['type', 'description']) && ! $openMeasure;
    $openReturn = $errors->has('stock');
    $openTransfer = $errors->hasAny(['transfer', 'base_id']);
    $openRetire = $errors->hasAny(['reason_id', 'photos']);
    $locationText = $loc?->unit
        ? 'En la unidad '.$loc->unit->plate.($loc->position ? ' · '.$loc->position->name : '')
        : trim(($loc?->location_kind?->label() ?? 'Sin ubicación').($loc?->base ? ' · '.$loc->base->name : ''));
@endphp

<x-page-header kicker="Cubierta" :title="$tire->displayName()" :subtitle="$tire->fullName().' · '.$tire->size->displayName()" :crumbs="[
    ['label' => 'Tablero', 'url' => route('dashboard')],
    ['label' => 'Neumáticos', 'url' => route('tires.index')],
    ['label' => $tire->displayName()],
]">
    <x-slot:actions>
        <a href="{{ route('tires.life-report', $tire) }}" class="btn btn-primary" target="_blank">Informe de vida</a>
        <a href="{{ route('tires.print', $tire) }}" class="btn btn-ghost" target="_blank">Ficha corta</a>
        <a href="{{ route('tires.index') }}" class="btn btn-ghost"><x-icon name="back" class="w-4 h-4" /> Listado</a>
        @if($user->role->canManageAbm())
            @if(request('edit'))
                <a href="{{ route('tires.show', $tire) }}" class="btn btn-ghost">Cancelar edición</a>
            @else
                <a href="{{ route('tires.show', [$tire, 'edit' => 1]) }}" class="btn btn-dark">Editar ficha</a>
            @endif
        @endif
    </x-slot:actions>
</x-page-header>

@if($repaired)
    <div class="flash flash--ok" role="status">Esta cubierta tiene un <strong>parche (reparación)</strong>. No abre vida nueva: se puede devolver al depósito e instalar de nuevo.</div>
@endif

{{-- 1. Resumen --}}
<section class="tire-summary" aria-labelledby="tireSummaryTitle">
    <div class="tire-summary__head">
        <h2 id="tireSummaryTitle" class="tire-summary__title">Situación actual</h2>
        <div class="tire-summary__chips">
            <x-status :tone="$tire->status->tone()">{{ $tire->status->label() }}</x-status>
            <x-status :tone="$tire->condition->tone()">{{ $tire->condition->label() }}</x-status>
        </div>
    </div>

    <p class="tire-summary__where">
        @if($loc?->unit)
            <a href="{{ route('units.show', $loc->unit) }}">{{ $locationText }}</a>
        @else
            {{ $locationText }}
        @endif
    </p>

    <dl class="tire-summary__stats">
        <div>
            <dt>Kilómetros</dt>
            <dd class="mono">{{ number_format($tire->accumulated_km) }}</dd>
        </div>
        <div>
            <dt>Vida</dt>
            <dd>{{ $tire->currentLifecycle?->life_number ?? 1 }} de {{ $tire->lifecycles->count() ?: 1 }}</dd>
        </div>
        <div>
            <dt>Profundidad mín.</dt>
            <dd class="mono">{{ $tire->current_tread_min ? $tire->current_tread_min.' mm' : 'Sin medir' }}</dd>
        </div>
        <div>
            <dt>DOT</dt>
            <dd class="mono">
                @if($tire->dot)
                    {{ $tire->dot }}@if($tire->manufactureLabel()) <span class="hint">· {{ $tire->manufactureLabel() }}</span>@endif
                @else
                    <span class="hint">Sin cargar</span>
                @endif
            </dd>
        </div>
        <div>
            <dt>Alta</dt>
            <dd>{{ $tire->purchased_at?->format('d/m/Y') ?? '—' }}</dd>
        </div>
        <div>
            <dt>Último evento</dt>
            <dd>@if($lastEvent){{ $lastEvent['at']?->format('d/m/Y H:i') ?? '—' }}{{ ! empty($lastEvent['headline']) ? ' · '.$lastEvent['headline'] : '' }}@else Todavía ninguno @endif</dd>
        </div>
    </dl>

    @if(! empty($forecast['narrative']))
        <div class="forecast-card forecast-card--{{ $forecast['status'] ?? 'unknown' }} tire-summary__forecast">
            <strong>Pronóstico</strong>
            <p>{{ $forecast['narrative'] }}</p>
            @if(($forecast['remaining_km'] ?? null) !== null)
                <p class="mono">{{ number_format($forecast['remaining_km']) }} km estimados hasta 4 mm · confianza {{ match($forecast['confidence'] ?? 'low') { 'high' => 'alta', 'medium' => 'media', default => 'baja' } }}</p>
            @endif
        </div>
    @endif

    @if($lastMeasure)
        <div class="tire-summary__measure">
            <h3>Última medición de profundidad</h3>
            <ul>
                @foreach($lastMeasure->readings as $reading)
                    <li>{{ $reading->zone?->name ?? 'Zona' }}: <span class="mono">{{ $reading->millimeters }} mm</span></li>
                @endforeach
            </ul>
            @if($lastMeasure->raises_alert)
                <p class="hint">Hay alerta de desgaste irregular.</p>
            @endif
        </div>
    @endif

    @if($tire->public_token)
        <div class="tire-summary__qr">
            <img src="{{ route('tires.qr', $tire) }}" alt="Código QR de {{ $tire->displayName() }}" width="96" height="96">
            <p class="hint">QR para escanear en campo</p>
        </div>
    @endif
</section>

{{-- 2. Qué hacer ahora --}}
@if($canOperate || $canReturn || $canTransfer || ($canRetire && $status !== 'DE_BAJA') || ($user->role->canManageAbm() && request('edit')))
<section class="action-cards-section" aria-labelledby="tireActionsTitle">
    <h2 id="tireActionsTitle" class="action-cards-section__title">Qué hacer ahora</h2>
    <p class="action-cards-section__lead">Tocá una tarjeta para abrir el formulario. Una acción por vez.</p>

    <div class="action-cards">
        @if($canOperate)
            <details class="action-card" @if($openMeasure) open @endif>
                <summary class="action-card__summary">
                    <span class="action-card__title">Medir el dibujo (mm)</span>
                    <span class="action-card__desc">Cargá la profundidad de cada franja. Sirve para ver el desgaste.</span>
                </summary>
                <div class="action-card__body">
                    <form method="POST" action="{{ route('tires.measurements.store', $tire) }}" class="space-y-3">
                        @csrf
                        @foreach($tire->size->zones as $i => $zone)
                            <label class="field">
                                <span>{{ $zone->name }} (mm)</span>
                                <input type="hidden" name="readings[{{ $i }}][zone_id]" value="{{ $zone->id }}">
                                <input name="readings[{{ $i }}][millimeters]" type="number" step="0.1" min="0" required
                                    value="{{ old('readings.'.$i.'.millimeters') }}"
                                    placeholder="Ej. 8.5"
                                    inputmode="decimal">
                            </label>
                        @endforeach
                        <button class="btn btn-primary action-card__btn">Guardar medición</button>
                    </form>
                </div>
            </details>

            <details class="action-card" @if($openIncident) open @endif>
                <summary class="action-card__summary">
                    <span class="action-card__title">Anotar un problema</span>
                    <span class="action-card__desc">Pinchadura, reparación, recapado u otro evento. Queda en el historial.</span>
                </summary>
                <div class="action-card__body">
                    <form method="POST" action="{{ route('tires.incidents.store', $tire) }}" class="space-y-3">
                        @csrf
                        <label class="field"><span>Tipo de problema</span>
                            <select name="type" required>
                                @foreach($incidentTypes as $type)
                                    <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field"><span>Descripción corta</span><input name="description" value="{{ old('description') }}" placeholder="Ej. Pinchó en ruta 9"></label>
                        <label class="field"><span>Observaciones</span><textarea name="notes" rows="3" placeholder="Detalle opcional">{{ old('notes') }}</textarea></label>
                        <p class="hint">Reparación o parche marcan la cubierta como reparada. Recapado (si tu rol lo permite) abre una vida nueva.</p>
                        <button class="btn btn-primary action-card__btn">Registrar problema</button>
                    </form>
                </div>
            </details>
        @endif

        @if($loc?->unit)
            <a class="action-card action-card--link" href="{{ route('units.show', $loc->unit) }}">
                <span class="action-card__title">Ir a la planilla de la unidad</span>
                <span class="action-card__desc">Montar, rotar, retirar o cambiar en el mapa de {{ $loc->unit->plate }}.</span>
            </a>
        @elseif($status === 'STOCK')
            <a class="action-card action-card--link" href="{{ route('units.index') }}">
                <span class="action-card__title">Ir a unidades para instalar</span>
                <span class="action-card__desc">Abrí la planilla de una patente y montá esta cubierta desde stock.</span>
            </a>
        @endif

        @if($canReturn)
            <details class="action-card" @if($openReturn) open @endif>
                <summary class="action-card__summary">
                    <span class="action-card__title">Devolver al depósito (stock)</span>
                    <span class="action-card__desc">Queda lista para instalar de nuevo. Un parche no es un recapado.</span>
                </summary>
                <div class="action-card__body">
                    <form method="POST" action="{{ route('tires.return-stock', $tire) }}" class="space-y-3" data-confirm="La cubierta vuelve a stock. ¿Continuar?">
                        @csrf
                        <label class="field"><span>Observaciones</span><textarea name="notes" rows="2" placeholder="Ej. Parche interno, queda lista">{{ old('notes') }}</textarea></label>
                        @if($canRetire && $status === 'EN_REPARACION')
                            <label class="field field--check">
                                <input type="checkbox" name="as_recap" value="1" @checked(old('as_recap'))>
                                <span>Volvió recapada (abre vida nueva)</span>
                            </label>
                        @endif
                        <x-field-error name="stock" />
                        <button class="btn btn-primary action-card__btn">Devolver a stock</button>
                    </form>
                </div>
            </details>
        @endif

        @if($canTransfer)
            <details class="action-card" @if($openTransfer) open @endif>
                <summary class="action-card__summary">
                    <span class="action-card__title">Cambiar de base</span>
                    <span class="action-card__desc">Mové esta cubierta de un depósito a otro. Solo si está en stock.</span>
                </summary>
                <div class="action-card__body">
                    <form method="POST" action="{{ route('tires.transfer-base', $tire) }}" class="space-y-3" data-confirm="¿Trasladar esta cubierta a otra base?">
                        @csrf
                        <label class="field"><span>Base destino</span>
                            <select name="base_id" required>
                                @foreach($bases as $base)
                                    <option value="{{ $base->id }}" @selected((int) old('base_id', $tire->currentLocation?->base_id) === (int) $base->id)>{{ $base->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field"><span>Observaciones</span><input name="notes" value="{{ old('notes') }}" placeholder="Opcional"></label>
                        <x-field-error name="transfer" />
                        <button class="btn btn-primary action-card__btn">Trasladar de base</button>
                    </form>
                </div>
            </details>
        @endif

        @if($canRetire && $status !== 'DE_BAJA')
            <details class="action-card action-card--danger" @if($openRetire) open @endif>
                <summary class="action-card__summary">
                    <span class="action-card__title">Sacar de circulación (baja)</span>
                    <span class="action-card__desc">Acción definitiva. No se puede volver a instalar. El historial se conserva.</span>
                </summary>
                <div class="action-card__body">
                    <form method="POST" action="{{ route('tires.retire', $tire) }}" class="space-y-3" enctype="multipart/form-data" data-confirm="La baja es definitiva e irreversible. El historial se conserva, pero esta cubierta no se puede reinstalar. ¿Dar de baja?">
                        @csrf
                        <label class="field"><span>Motivo</span>
                            <select name="reason_id" required>
                                @foreach($retirementReasons as $reason)
                                    <option value="{{ $reason->id }}" @selected((string) old('reason_id') === (string) $reason->id)>{{ $reason->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field"><span>Observaciones</span><textarea name="notes" rows="2" placeholder="Explicá por qué se da de baja" required>{{ old('notes') }}</textarea></label>
                        <label class="field">
                            <span>Fotos de la cubierta</span>
                            <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" capture="environment" multiple data-photo-input="#retirePhotoPreview">
                        </label>
                        <div id="retirePhotoPreview" class="photo-grid" aria-live="polite"></div>
                        <p class="hint">Hasta 6 fotos, JPG o PNG. En el celular se puede abrir la cámara.</p>
                        <button class="btn btn-danger action-card__btn">Confirmar baja</button>
                    </form>
                </div>
            </details>
        @endif

        @if($user->role->canManageAbm() && request('edit'))
            <details class="action-card" open>
                <summary class="action-card__summary">
                    <span class="action-card__title">Editar datos de la ficha</span>
                    <span class="action-card__desc">Número, DOT, marca, modelo, medida o condición. Solo administración.</span>
                </summary>
                <div class="action-card__body">
                    <form method="POST" action="{{ route('tires.update', $tire) }}" class="space-y-3">
                        @csrf
                        @method('PUT')
                        <label class="field"><span>Nº individual</span><input name="individual_number" type="number" min="1" value="{{ old('individual_number', $tire->individual_number) }}" required @error('individual_number') aria-invalid="true" @enderror><x-field-error name="individual_number" /></label>
                        <label class="field"><span>Motivo del cambio de número</span><input name="number_reason" value="{{ old('number_reason') }}" placeholder="Obligatorio si cambiás el número"><x-field-error name="number_reason" /></label>
                        <label class="field">
                            <span>DOT (garantía)</span>
                            <input name="dot" value="{{ old('dot', $tire->dot) }}" maxlength="20" placeholder="Ej. 1A3B4C0524" autocomplete="off" @error('dot') aria-invalid="true" @enderror>
                            <span class="hint">Código en el flanco. Los últimos 4 dígitos son semana y año de fabricación.</span>
                            <x-field-error name="dot" />
                        </label>
                        <label class="field"><span>Marca</span>
                            <select name="tire_brand_id" required>
                                @foreach($brands as $brand)
                                    <option value="{{ $brand->id }}" @selected((int) old('tire_brand_id', $tire->tire_brand_id) === (int) $brand->id)>{{ $brand->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field"><span>Modelo</span>
                            <select name="tire_model_id" required>
                                @foreach($models as $model)
                                    <option value="{{ $model->id }}" @selected((int) old('tire_model_id', $tire->tire_model_id) === (int) $model->id)>{{ $model->brand?->name }} {{ $model->code }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field"><span>Medida</span>
                            <select name="tire_size_id" required>
                                @foreach($sizes as $size)
                                    <option value="{{ $size->id }}" @selected((int) old('tire_size_id', $tire->tire_size_id) === (int) $size->id)>{{ $size->displayName() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field"><span>Condición</span>
                            <select name="condition" required>
                                @foreach($conditions as $condition)
                                    <option value="{{ $condition->value }}" @selected(old('condition', $tire->condition->value) === $condition->value)>{{ $condition->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button class="btn btn-primary action-card__btn">Guardar cambios</button>
                    </form>
                </div>
            </details>
        @endif
    </div>
</section>
@elseif($status === 'DE_BAJA')
    <div class="flash flash--warn" role="status">Esta cubierta está de baja. Solo se consulta el historial; no se puede operar.</div>
@endif

{{-- 3. Historial --}}
<x-panel title="Historial de esta cubierta" class="mt-6" :flush="true">
    <div class="panel__body pb-0">
        <p class="hint mb-3">De más reciente a más antiguo. Solo operaciones guardadas (montar, medir, rotar, incidencias…). Abrir esta pantalla no genera registros.</p>
    </div>
    <div class="panel__body pt-0">
        @if($timelineNewest->isEmpty())
            <x-empty title="Todavía no hay eventos" text="Cuando se monte, mida o registre un problema, el historial aparece acá." />
        @else
            <p class="timeline__legend">
                <span><i class="tl-dot tl-dot--green"></i> Alta o montaje</span>
                <span><i class="tl-dot tl-dot--orange"></i> Problema</span>
                <span><i class="tl-dot tl-dot--amber"></i> Cambio de lugar</span>
                <span><i class="tl-dot tl-dot--blue"></i> Vida / recap</span>
                <span><i class="tl-dot tl-dot--red"></i> Baja o alerta</span>
            </p>
            <ol class="timeline">
                @foreach($timelineNewest as $item)
                    <li class="tl-card tl-card--{{ $item['tone'] }}">
                        <div class="tl-card__rail" aria-hidden="true"><i></i></div>
                        <div class="tl-card__body">
                            <div class="tl-card__meta">
                                <span class="tl-card__kind">{{ $item['kind_label'] }}</span>
                                <span>{{ $item['at']?->format('d/m/Y H:i') ?? '—' }}@if(! empty($item['user'])) · {{ $item['user'] }}@endif</span>
                            </div>
                            <h3 class="tl-card__title">{{ $item['headline'] }}</h3>
                            @if(! empty($item['summary']))
                                <p class="tl-card__summary">{{ $item['summary'] }}</p>
                            @endif
                            @if($item['steps']->isNotEmpty())
                                <ol class="tl-steps">
                                    @foreach($item['steps'] as $step)
                                        <li>
                                            <span>{{ $step['kind_label'] }}</span>
                                            {{ $step['title'] }}@if(! empty($step['body'])) — {{ $step['body'] }}@endif
                                        </li>
                                    @endforeach
                                </ol>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</x-panel>

{{-- 4. Secciones secundarias --}}
@if($costTotal > 0 || $tire->costEntries->isNotEmpty())
<x-panel title="Costos" class="mt-6">
    <p class="mono mb-3">Total: $ {{ number_format($costTotal, 2, ',', '.') }}
        @if($tire->accumulated_km)
            · $ / km: {{ number_format($costTotal / $tire->accumulated_km, 4, ',', '.') }}
        @endif
    </p>
    @if($tire->costEntries->isNotEmpty())
        <ul class="text-sm space-y-1">
            @foreach($tire->costEntries->sortByDesc('occurred_at') as $entry)
                <li>
                    <span class="mono">{{ $entry->occurred_at->format('d/m/Y') }}</span>
                    · {{ $entry->categoryLabel() }}
                    · <span class="mono">$ {{ number_format($entry->amount, 2, ',', '.') }}</span>
                    @if($entry->fleetUnit || $entry->unitPosition)
                        <span class="hint">— {{ $entry->fleetUnit?->plate }} {{ $entry->unitPosition?->name }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-panel>
@endif

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

@php $retirePhotos = $tire->photos->where('kind', 'RETIRE'); @endphp
@if($retirePhotos->isNotEmpty())
<x-panel title="Fotos de baja" class="mt-6">
    <div class="photo-grid">
        @foreach($retirePhotos as $photo)
            <a href="{{ route('tires.photos.show', [$tire, $photo]) }}" target="_blank" class="photo-grid__item">
                <img src="{{ route('tires.photos.show', [$tire, $photo]) }}" alt="Foto de baja {{ $photo->original_name }}">
                <span>{{ $photo->captured_at?->format('d/m/Y H:i') }}</span>
            </a>
        @endforeach
    </div>
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
@endsection
