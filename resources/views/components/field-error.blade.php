@props(['name'])
@error($name)
    <p {{ $attributes->class('field-error') }} id="err-{{ str_replace(['[', ']'], ['-', ''], $name) }}" role="alert">{{ $message }}</p>
@enderror
