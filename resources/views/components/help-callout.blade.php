@props(['title' => 'Cómo usar esto'])

<details {{ $attributes->class('help-callout') }}>
    <summary class="help-callout__summary">
        <span class="help-callout__ico" aria-hidden="true">?</span>
        <span>{{ $title }}</span>
    </summary>
    <div class="help-callout__body">
        {{ $slot }}
    </div>
</details>
