@props(['axle', 'slots', 'prefix', 'interactive' => false, 'marks' => []])

@php
    $left = $slots->filter(fn ($slot) => $slot['position']->side === 'IZQ')->sortBy('position.grid_col');
    $right = $slots->filter(fn ($slot) => $slot['position']->side === 'DER')->sortBy('position.grid_col');
    $isSteer = $left->count() === 1 && $right->count() === 1 && $slots->every(fn ($slot) => ! $slot['position']->dual);
    $role = $slots->first()['position']->axleRole();
    $roleKey = $slots->first()['position']->axle_role;
    $liftable = $slots->contains(fn ($slot) => $slot['position']->is_liftable);
@endphp

<div class="schematic-axle {{ $isSteer ? 'schematic-axle--steer' : '' }} schematic-axle--{{ $roleKey }} {{ $liftable ? 'schematic-axle--lift' : '' }}">
    <span class="schematic-axle__n">E{{ $axle }}<em>{{ $role }}</em></span>
    <div class="schematic-axle__side">
        @foreach($left as $slot)
            <x-tire-box :tire="$slot['tire']" :position="$slot['position']" :prefix="$prefix" :interactive="$interactive" :mark="$marks[$slot['position']->id] ?? null" />
        @endforeach
    </div>
    <div class="schematic-axle__beam" aria-hidden="true"></div>
    <div class="schematic-axle__side">
        @foreach($right as $slot)
            <x-tire-box :tire="$slot['tire']" :position="$slot['position']" :prefix="$prefix" :interactive="$interactive" :mark="$marks[$slot['position']->id] ?? null" />
        @endforeach
    </div>
</div>
