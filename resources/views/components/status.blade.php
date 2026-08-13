@props(['tone' => 'slate'])

<span {{ $attributes->class('st st--'.$tone) }}>
    <i></i>{{ $slot }}
</span>
