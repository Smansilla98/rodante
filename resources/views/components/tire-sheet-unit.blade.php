@props(['unit', 'current', 'layout'])

@php
    $prefix = $unit->type->sheetPrefix();
    $axles = $layout->where(fn ($slot) => ! $slot['position']->is_spare)->groupBy(fn ($slot) => $slot['position']->axle_number);
    $spare = $layout->first(fn ($slot) => $slot['position']->is_spare);
@endphp

<section class="tire-sheet-unit">
    <h3 class="tire-sheet-unit__title">
        {{ $unit->type->sheetLabel() }}
        <span class="tire-sheet-unit__pfx">{{ $prefix }}</span>
        PATENTE:
        @if($unit->is($current))
            <strong>{{ $unit->plate }}</strong>
        @else
            <a href="{{ route('units.show', $unit) }}">{{ $unit->plate }}</a>
        @endif
    </h3>

    <div class="tire-sheet-orient">
        <span>IZQ</span>
        <span class="tire-sheet-front">↑ Frente</span>
        <span>DER</span>
    </div>

    @foreach($axles as $axle => $slots)
        @php
            $left = $slots->filter(fn ($slot) => $slot['position']->side === 'IZQ')->sortBy('position.grid_col');
            $right = $slots->filter(fn ($slot) => $slot['position']->side === 'DER')->sortBy('position.grid_col');
            $isSteer = $left->count() === 1 && $right->count() === 1;
            $role = $slots->first()['position']->axleRole($unit->hasOdometer(), $isSteer);
        @endphp
        <div class="tire-sheet-axle {{ $isSteer ? 'tire-sheet-axle--steer' : '' }}">
            <span class="tire-sheet-axle__n">E{{ $axle }}<em>{{ $role }}</em></span>
            <div class="tire-sheet-axle__side">
                @foreach($left as $slot)
                    <x-tire-box :tire="$slot['tire']" :position="$slot['position']" :prefix="$prefix" />
                @endforeach
            </div>
            <div class="tire-sheet-axle__gap" aria-hidden="true"></div>
            <div class="tire-sheet-axle__side">
                @foreach($right as $slot)
                    <x-tire-box :tire="$slot['tire']" :position="$slot['position']" :prefix="$prefix" />
                @endforeach
            </div>
        </div>
    @endforeach

    @if($spare)
        <div class="tire-sheet-spare">
            <span>AUXILIO</span>
            <x-tire-box :tire="$spare['tire']" :position="$spare['position']" :prefix="$prefix" />
        </div>
    @endif
</section>
