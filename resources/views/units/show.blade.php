@extends('layouts.app')
@section('kicker', 'Planilla')
@section('title', $sheetUnits->pluck('plate')->join(' + '))
@section('page_class', 'app-page--planilla')
@section('content')
@php
    $coupled = $unit->coupledPartner();
    $lastKm = $unit->hasOdometer()
        ? $unit->current_odometer
        : ($unit->currentCouplingAsTrailer?->tractor?->current_odometer);
@endphp

<div class="planilla-bar">
    <div class="planilla-bar__id">
        <span class="planilla-bar__k">Planilla</span>
        <nav class="crumbs crumbs--lite" aria-label="Migas de pan">
            <a href="{{ route('dashboard') }}">Tablero</a>
            <span class="crumbs__sep" aria-hidden="true">/</span>
            <a href="{{ route('units.index') }}">Unidades</a>
            <span class="crumbs__sep" aria-hidden="true">/</span>
            <span>{{ $unit->plate }}</span>
        </nav>
        <h1>{{ $sheetUnits->pluck('plate')->join(' + ') }}</h1>
        <p>{{ $unit->type->name }} · {{ $unit->configuration->label() }}{{ $unit->specSummary() ? ' · '.$unit->specSummary() : '' }} · {{ $unit->fleet->name }}</p>
    </div>
    <div class="planilla-bar__meta">
        <span><em>Base</em> {{ $unit->base->name }}</span>
        <span><em>Fecha</em> {{ now()->format('d/m/Y') }}</span>
        @if($lastKm !== null)
            <span><em>Última lectura</em> {{ number_format($lastKm) }} km</span>
        @endif
    </div>
    <a href="{{ route('units.index') }}" class="btn btn-ghost btn-sm"><x-icon name="back" class="w-4 h-4" /> Unidades</a>
    @if(auth()->user()->role->canManageAbm())
        <a href="{{ route('units.edit', $unit) }}" class="btn btn-ghost btn-sm">Editar</a>
    @endif
</div>

<div class="planilla-work {{ $canOperate ? 'planilla-work--ops' : '' }}">
    @if($canOperate)
        <aside class="stock-rail stock-rail--tools no-print">
            <h2 class="stock-rail__title">Auxilio</h2>
            <div class="refaccion-drop" id="refaccionDrop" data-spare-slot="{{ $spareSlotId }}" data-slot="{{ $spareSlotId }}">
                @php $spareLoc = $unit->locations->firstWhere('position_id', $spareSlotId); @endphp
                @if($spareLoc?->tire)
                    <p class="refaccion-drop__tire">{{ $spareLoc->tire->displayName() }}</p>
                    <p class="refaccion-drop__meta">Montada en auxilio</p>
                @else
                    <p class="refaccion-drop__empty">Tocá el auxilio del mapa para instalar</p>
                @endif
            </div>

            @if($rotationPatterns)
                <h2 class="stock-rail__title">Rotación</h2>
                <div class="pattern-list">
                    @foreach($rotationPatterns as $pattern)
                        <button type="button" class="pattern-btn" data-pattern="{{ $pattern['code'] }}"
                            @disabled(! $pattern['ready'])
                            title="{{ $pattern['ready'] ? $pattern['hint'] : $pattern['blocked'] }}">
                            <x-rotation-mini :code="$pattern['code']" />
                            <span>{{ $pattern['name'] }}</span>
                        </button>
                    @endforeach
                </div>
            @endif
        </aside>
    @endif

    <x-tire-sheet :units="$sheetUnits" :current="$unit" :interactive="$canOperate" />

    @if($canOperate)
        <aside class="recambio-dock no-print" id="recambioDock" data-stock-url="{{ route('units.stock', $unit) }}" data-last-km="{{ $lastKm }}">
            <h2 class="recambio-dock__title">Ubicación</h2>
            <p class="recambio-dock__idle" id="recambioIdle">Tocá una cubierta del mapa. El stock aparece recién cuando elegís <strong>Cambio</strong> o una ubicación vacía, y solo del mismo tipo.</p>

            <div id="recambioPanel" hidden>
                <p class="recambio-dock__slot" id="recambioSlot"></p>
                <p class="recambio-dock__role" id="recambioRole"></p>

                <form method="POST" action="{{ route('units.slot', $unit) }}" id="recambioInstall" hidden>
                    @csrf
                    <input type="hidden" name="action" value="install">
                    <input type="hidden" name="position_id" id="installPosition">
                    <input type="hidden" name="expected_tire_id" id="installExpected" value="">
                    <p class="recambio-dock__hint" id="installHint">Ubicación vacía. Elegí una cubierta compatible del mismo tipo.</p>
                    <label class="field">
                        <span>Buscar</span>
                        <input type="search" id="installSearch" class="inp" placeholder="Nº o modelo" autocomplete="off">
                    </label>
                    <label class="field">
                        <span>Entra</span>
                        <select name="tire_id" id="installTire" class="inp" required></select>
                    </label>
                    <p class="recambio-dock__empty" id="installEmpty" hidden>No hay cubiertas compatibles en stock.</p>
                    <x-slot-odometer :last-km="$lastKm" id="installOdometer" />
                    <button class="btn btn-primary w-full mt-3" id="installSubmit">Instalar</button>
                </form>

                <div id="recambioMounted" hidden>
                    <div class="recambio-facts" id="tireFacts">
                        <p class="recambio-facts__name" id="mountedName"></p>
                        <p class="recambio-facts__meta" id="mountedMeta"></p>
                        <p class="recambio-facts__link"><a id="mountedLink" href="#">Ver ficha completa</a></p>
                    </div>

                    <div class="slot-actions" id="slotActions">
                        <button type="button" class="slot-actions__btn" data-action="cambio">Cambio</button>
                        <button type="button" class="slot-actions__btn" data-action="pinchadura">Pinchadura</button>
                        <button type="button" class="slot-actions__btn" data-action="rotacion">Rotación</button>
                        <button type="button" class="slot-actions__btn" data-action="retirar">Retirar</button>
                        <button type="button" class="slot-actions__btn" data-action="incidencia">Incidencia</button>
                        <button type="button" class="slot-actions__btn" data-action="medicion">Medición</button>
                    </div>

                    <form method="POST" action="{{ route('units.slot', $unit) }}" id="formCambio" hidden>
                        @csrf
                        <input type="hidden" name="action" value="cambio">
                        <input type="hidden" name="position_id" id="cambioPosition">
                        <input type="hidden" name="expected_tire_id" class="expected-tire">
                        <dl class="ot-ticket" aria-label="Orden de recambio">
                            <div><dt>Sale</dt><dd id="cambioSale">—</dd></div>
                            <div><dt>Entra</dt><dd id="cambioEntra">Elegí la cubierta nueva</dd></div>
                            <div><dt>Lugar</dt><dd id="cambioLugar">—</dd></div>
                        </dl>
                        <p class="recambio-dock__hint" id="cambioHint">El stock se carga al elegir Cambio y coincide con el tipo de esta cubierta.</p>
                        <label class="field">
                            <span>Buscar recambio</span>
                            <input type="search" id="cambioSearch" class="inp" placeholder="Nº o modelo" autocomplete="off">
                        </label>
                        <label class="field">
                            <span>Cubierta que entra</span>
                            <select name="tire_id" id="cambioTire" class="inp" required></select>
                        </label>
                        <p class="recambio-dock__empty" id="cambioEmpty" hidden>No hay cubiertas del mismo tipo en stock.</p>
                        <x-slot-odometer :last-km="$lastKm" id="cambioOdometer" />
                        <label class="field mt-2">
                            <span>Nota</span>
                            <input name="notes" class="inp" placeholder="Opcional. Ej. se cambió 1 cubierta eje portador">
                        </label>
                        <button class="btn btn-primary w-full mt-3" id="cambioSubmit">Confirmar cambio</button>
                    </form>

                    <form method="POST" action="{{ route('units.slot', $unit) }}" id="formPinchadura" hidden>
                        @csrf
                        <input type="hidden" name="action" value="pinchadura">
                        <input type="hidden" name="position_id" id="pinchaduraPosition">
                        <input type="hidden" name="expected_tire_id" class="expected-tire">
                        <p class="recambio-dock__idle mb-3">Registra la pinchadura y manda la cubierta a reparación. La ubicación queda libre.</p>
                        <x-slot-odometer :last-km="$lastKm" />
                        <label class="field">
                            <span>Nota</span>
                            <input name="notes" class="inp" placeholder="Opcional">
                        </label>
                        <button class="btn btn-dark w-full mt-3">Registrar pinchadura</button>
                    </form>

                    <form method="POST" action="{{ route('units.slot', $unit) }}" id="formRotacion" hidden>
                        @csrf
                        <input type="hidden" name="action" value="rotacion">
                        <input type="hidden" name="position_id" id="rotacionFrom">
                        <input type="hidden" name="expected_tire_id" class="expected-tire">
                        <input type="hidden" name="expected_to_tire_id" id="expectedToTire">
                        <label class="field">
                            <span>Rotar o intercambiar con</span>
                            <select name="to_position_id" id="rotatePosition" class="inp" required></select>
                        </label>
                        <p class="recambio-dock__empty" id="rotateEmpty" hidden>No hay otra ubicación compatible.</p>
                        <x-slot-odometer :last-km="$lastKm" />
                        <label class="field mt-2">
                            <span>Nota</span>
                            <input name="notes" class="inp" placeholder="Opcional">
                        </label>
                        <button class="btn btn-ghost w-full mt-3" id="rotateSubmit">Confirmar rotación</button>
                    </form>

                    <form method="POST" action="{{ route('units.slot', $unit) }}" id="formRetirar" hidden>
                        @csrf
                        <input type="hidden" name="action" value="retirar">
                        <input type="hidden" name="position_id" id="retirarPosition">
                        <input type="hidden" name="expected_tire_id" class="expected-tire">
                        <p class="recambio-dock__idle mb-3">Retira la cubierta de esta ubicación sin instalar otra. El km se asienta solo en esta cubierta.</p>
                        <x-slot-odometer :last-km="$lastKm" />
                        <label class="field">
                            <span>Motivo</span>
                            <select name="reason_id" class="inp" required>
                                <option value="">Elegir motivo</option>
                                @foreach($reasons as $reason)
                                    <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field mt-2">
                            <span>Destino</span>
                            <select name="destination" class="inp" required>
                                @foreach($destinations as $destination)
                                    <option value="{{ $destination->value }}" @selected($destination === \App\Enums\TireStatus::Stock)>{{ $destination->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field mt-2">
                            <span>Nota</span>
                            <input name="notes" class="inp" placeholder="Opcional">
                        </label>
                        <button class="btn btn-dark w-full mt-3">Confirmar retiro</button>
                    </form>

                    <form method="POST" action="{{ route('units.slot', $unit) }}" id="formIncidencia" hidden>
                        @csrf
                        <input type="hidden" name="action" value="incidencia">
                        <input type="hidden" name="position_id" id="incidenciaPosition">
                        <input type="hidden" name="expected_tire_id" class="expected-tire">
                        <p class="recambio-dock__idle mb-3">La cubierta sigue montada. Usá pinchadura o cambio si hay que retirarla.</p>
                        <x-slot-odometer :last-km="$lastKm" />
                        <label class="field">
                            <span>Tipo</span>
                            <select name="incident_type" class="inp" required>
                                <option value="">Elegir tipo</option>
                                @foreach($incidentTypes as $type)
                                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field mt-2">
                            <span>Descripción</span>
                            <input name="description" class="inp" placeholder="Opcional">
                        </label>
                        <label class="field mt-2">
                            <span>Nota</span>
                            <input name="notes" class="inp" placeholder="Opcional">
                        </label>
                        <button class="btn btn-dark w-full mt-3">Registrar incidencia</button>
                    </form>

                    <form method="POST" action="{{ route('units.slot', $unit) }}" id="formMedicion" hidden>
                        @csrf
                        <input type="hidden" name="action" value="medicion">
                        <input type="hidden" name="position_id" id="medicionPosition">
                        <input type="hidden" name="expected_tire_id" class="expected-tire">
                        <p class="recambio-dock__idle mb-3">Cargá la profundidad de cada franja. Un desgaste lateral disparado genera alerta.</p>
                        <x-slot-odometer :last-km="$lastKm" />
                        <div id="measureFields" class="space-y-2"></div>
                        <p class="recambio-dock__empty" id="measureEmpty" hidden>Esta medida no tiene franjas de profundidad configuradas.</p>
                        <label class="field mt-2">
                            <span>Nota</span>
                            <input name="notes" class="inp" placeholder="Opcional">
                        </label>
                        <button class="btn btn-primary w-full mt-3" id="measureSubmit">Guardar medición</button>
                    </form>
                </div>
            </div>

            <form method="POST" action="{{ route('units.slot', $unit) }}" id="formPatron" hidden>
                @csrf
                <input type="hidden" name="action" value="patron">
                <input type="hidden" name="pattern" id="patronCode">
                <p class="recambio-dock__slot">Esquema de rotación</p>
                <p class="recambio-dock__idle mb-3" id="patronHint"></p>
                <x-slot-odometer :last-km="$lastKm" />
                <label class="field">
                    <span>Nota</span>
                    <input name="notes" class="inp" placeholder="Opcional">
                </label>
                <button class="btn btn-primary w-full mt-3" id="patronSubmit">Aplicar esquema</button>
            </form>
        </aside>
        <script type="application/json" id="slotMap">@json($slotMap)</script>
        <script type="application/json" id="rotationPatterns">@json($rotationPatterns)</script>
        <div id="slotMenu" class="slot-menu" hidden>
            <button type="button" data-menu="ficha">Ver ficha</button>
            <button type="button" data-menu="cambio">Cambio</button>
            <button type="button" data-menu="rotar">Rotar / intercambiar</button>
            <button type="button" data-menu="quitar">Quitar cubierta</button>
            <button type="button" data-menu="incidencia">Incidencia</button>
            <button type="button" data-menu="medicion">Medición</button>
        </div>
    @endif
</div>

<div class="grid lg:grid-cols-3 gap-5 mb-6 no-print">
    <x-panel title="Datos">
        <div class="dl">
            <div><span>Base</span>{{ $unit->base->name }}</div>
            <div><span>Equipo</span>{{ $unit->brand }} {{ $unit->model_name }}</div>
            <div><span>Configuración</span>{{ $unit->configuration->label() }}</div>
            @if($unit->configuration->description)
                <div><span>Layout</span>{{ $unit->configuration->description }}</div>
            @endif
            @if($unit->specSummary())
                <div><span>Chasis</span>{{ $unit->specSummary() }}</div>
            @endif
            <div><span>Odómetro</span>{{ $unit->hasOdometer() ? number_format($unit->current_odometer).' km' : 'Usa el del tractor acoplado' }}</div>
            <div><span>Acoplado</span>{{ $coupled?->plate ?? 'Sin acoplar' }}</div>
        </div>
        @if(! $unit->hasOdometer() && auth()->user()->role->canWrite())
            <form method="POST" action="{{ route('units.specs', $unit) }}" class="flex flex-wrap gap-2 mt-4">
                @csrf
                <select name="tire_width" class="inp flex-1" required>
                    <option value="295" @selected($unit->allowedTireWidth() === 295)>Lineal 295</option>
                    <option value="385" @selected($unit->allowedTireWidth() === 385)>Lineal 385 (gomón)</option>
                </select>
                <button class="btn btn-ghost btn-sm">Guardar medida</button>
            </form>
        @endif
    </x-panel>

    @if(auth()->user()->role->canManageCouplings())
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
    @endif

        @if(auth()->user()->role->canChangeConfiguration())
        <x-panel title="Cambio de configuración">
            <form method="POST" action="{{ route('units.configuration', $unit) }}" class="space-y-3" data-confirm="Las cubiertas instaladas pasan a stock. El historial se conserva. ¿Cambiar la configuración?">
                @csrf
                <p class="hint">Al cambiar el layout, todo lo montado se retira a stock. No se puede dejar una cubierta en el aire.</p>
                <select name="unit_configuration_id" class="inp">
                    @foreach($configurations as $cfg)
                        <option value="{{ $cfg->id }}" @selected($cfg->id===$unit->unit_configuration_id)>{{ $cfg->label() }}</option>
                    @endforeach
                </select>
                @if($unit->hasOdometer())
                    <label class="field"><span>Odómetro al cambiar</span>
                        <input name="odometer" type="number" min="0" class="inp" required value="{{ $unit->current_odometer }}">
                    </label>
                @endif
                <input name="reason" class="inp" placeholder="Motivo (urbana → ripio)" required>
                <button class="btn btn-ghost btn-sm">Cambiar</button>
            </form>
        </x-panel>
        @endif
</div>

<x-panel title="Historial en esta patente" :flush="true" class="no-print planilla-history">
    <x-content-table>
        <thead>
            <tr>
                <th scope="col">Fecha</th>
                <th scope="col">Evento</th>
                <th scope="col">Cubierta</th>
                <th scope="col">Detalle</th>
            </tr>
        </thead>
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
    </x-content-table>
</x-panel>
@endsection
