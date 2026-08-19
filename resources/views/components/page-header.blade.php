@props(['kicker' => null, 'title', 'subtitle' => null, 'crumbs' => []])

<div {{ $attributes->class('page-hero') }}>
    <div class="min-w-0">
        @if(count($crumbs))
            <nav class="crumbs" aria-label="Migas de pan">
                @foreach($crumbs as $crumb)
                    @if(!empty($crumb['url']) && ! $loop->last)
                        <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                        <span class="crumbs__sep" aria-hidden="true">/</span>
                    @else
                        <span>{{ $crumb['label'] }}</span>
                    @endif
                @endforeach
            </nav>
        @elseif($kicker)
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
