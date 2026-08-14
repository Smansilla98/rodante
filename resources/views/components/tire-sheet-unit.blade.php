@props(['unit', 'current', 'layout', 'interactive' => false])

@php
    $prefix = $unit->type->sheetPrefix();
    $axles = $layout->where(fn ($slot) => ! $slot['position']->is_spare)->groupBy(fn ($slot) => $slot['position']->axle_number);
    $spares = $layout->where(fn ($slot) => $slot['position']->is_spare)->values();
    $onThisUnit = $interactive && $unit->is($current);
    $marks = [];
    $n = 1;
    foreach ($axles as $slots) {
        $left = $slots->filter(fn ($slot) => $slot['position']->side === 'IZQ')->sortBy('position.grid_col');
        $right = $slots->filter(fn ($slot) => $slot['position']->side === 'DER')->sortBy('position.grid_col');
        foreach ($left as $slot) {
            $marks[$slot['position']->id] = $n++;
        }
        foreach ($right as $slot) {
            $marks[$slot['position']->id] = $n++;
        }
    }
    foreach ($spares as $slot) {
        $marks[$slot['position']->id] = $n++;
    }
@endphp

<section class="tire-sheet-unit">
    <h3 class="tire-sheet-unit__title">
        <span class="tire-sheet-unit__kind">{{ $unit->type->sheetLabel() }}</span>
        @if($unit->is($current))
            <strong>{{ $unit->plate }}</strong>
        @else
            <a href="{{ route('units.show', $unit) }}">{{ $unit->plate }}</a>
        @endif
        @if($unit->allowedTireWidth())
            <span class="tire-sheet-unit__pfx">{{ $unit->allowedTireWidth() }}</span>
        @endif
    </h3>

    <div class="schematic {{ $onThisUnit ? 'schematic--live' : '' }}">
        <svg class="schematic__arrows" hidden aria-hidden="true"></svg>
        <div class="schematic__frame">
            <div class="schematic__spine" aria-hidden="true"></div>
            @foreach($axles as $axle => $slots)
                <x-tire-sheet-axle :axle="$axle" :slots="$slots" :prefix="$prefix" :interactive="$onThisUnit" :marks="$marks" />
            @endforeach
        </div>
        @if($spares->isNotEmpty())
            <div class="schematic-spare">
                <span>{{ $spares->count() > 1 ? 'Auxilios' : 'Auxilio' }}</span>
                <div class="schematic-spare__row">
                    @foreach($spares as $slot)
                        <x-tire-box
                            :tire="$slot['tire']"
                            :position="$slot['position']"
                            :prefix="$prefix"
                            :interactive="$onThisUnit"
                            :mark="$marks[$slot['position']->id] ?? null"
                        />
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</section>
