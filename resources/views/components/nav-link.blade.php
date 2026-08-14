@props(['href', 'icon', 'label', 'match' => null])
@php
    $isActive = $match ? request()->routeIs(...(array) $match) : false;
@endphp
<a href="{{ $href }}" @class(['sb-link', 'is-active' => $isActive]) @if($isActive) aria-current="page" @endif>
    <span class="sb-ico-wrap" aria-hidden="true">
        <x-icon :name="$icon" class="sb-ico" />
    </span>
    <span>{{ $label }}</span>
</a>
