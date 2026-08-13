@props(['title' => null, 'flush' => false])

<section {{ $attributes->class('panel') }}>
    @if($title || isset($toolbar))
        <div class="panel__head">
            @if($title)<h2 class="panel__title">{{ $title }}</h2>@endif
            @isset($toolbar)<div class="panel__toolbar">{{ $toolbar }}</div>@endisset
        </div>
    @endif
    <div @class(['panel__body' => ! $flush])>
        {{ $slot }}
    </div>
</section>
