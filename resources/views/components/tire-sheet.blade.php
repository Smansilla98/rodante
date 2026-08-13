@props(['units', 'current'])

<section class="tire-sheet">
    <header class="tire-sheet__header">
        <div>BASE: <span>{{ $current->base->name }}</span></div>
        <div>FECHA: <span>{{ now()->format('d/m/Y') }}</span></div>
    </header>
    <p class="tire-sheet__hint">Izquierda y derecha desde el conductor, mirando al frente. Cada cajón es una posición: dual interna y externa se registran aparte. El acoplado tiene su propio prefijo para no chocar con el tractor al recambiar.</p>

    @foreach($units as $sheetUnit)
        <x-tire-sheet-unit
            :unit="$sheetUnit"
            :current="$current"
            :layout="$sheetUnit->tireLayout()"
        />
    @endforeach

    <div class="tire-legend">
        <span><i class="lg lg--ok"></i> ≥ 8 mm</span>
        <span><i class="lg lg--warn"></i> 4–8 mm</span>
        <span><i class="lg lg--critical"></i> ≤ 4 mm</span>
        <span><i class="lg lg--unknown"></i> Sin medición</span>
        <span><i class="lg lg--empty"></i> Vacío</span>
    </div>

    <div class="tire-matrix">
        <table>
            <thead>
                <tr>
                    <th>Posición</th>
                    <th>Aplicación</th>
                    <th>Cubierta</th>
                    <th>mm</th>
                    <th>Km</th>
                </tr>
            </thead>
            <tbody>
            @foreach($units as $sheetUnit)
                @php $prefix = $sheetUnit->type->sheetPrefix(); @endphp
                @foreach($sheetUnit->tireLayout() as $slot)
                    @php
                        $pos = $slot['position'];
                        $tire = $slot['tire'];
                        $isSteer = ! $pos->is_spare && $pos->axle_number === 1 && $sheetUnit->hasOdometer();
                    @endphp
                    <tr>
                        <td class="mono">{{ $pos->sheetCode($prefix) }}</td>
                        <td>{{ $pos->axleRole($sheetUnit->hasOdometer(), $isSteer) }}</td>
                        <td>
                            @if($tire)
                                <a href="{{ route('tires.show', $tire) }}">{{ $tire->displayName() }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="mono">{{ $tire?->current_tread_min ? $tire->current_tread_min.' mm' : '—' }}</td>
                        <td class="mono">{{ $tire ? number_format($tire->accumulated_km) : '—' }}</td>
                    </tr>
                @endforeach
            @endforeach
            </tbody>
        </table>
    </div>
</section>
