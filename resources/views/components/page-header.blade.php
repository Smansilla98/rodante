@props(['kicker' => null, 'title', 'subtitle' => null])

<div {{ $attributes->class('page-hero') }}>
    <div class="min-w-0">
        @if($kicker)
            <div class="page-kicker">{{ $kicker }}</div>
        @endif
        <h1 class="page-title">{{ $title }}</h1>
        @if($subtitle)
            <p class="page-sub">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="page-hero__actions">{{ $actions }}</div>
    @endisset
</div>
