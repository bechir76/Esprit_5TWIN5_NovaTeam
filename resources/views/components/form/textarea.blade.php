@props(['name', 'label', 'value' => null, 'id' => null, 'hint' => null])

@php($id ??= $name)

<div @class(['field', 'is-invalid' => $errors->has($name)])>
    <label for="{{ $id }}">{{ $label }}</label>
    <textarea {{ $attributes->class(['input']) }} id="{{ $id }}" name="{{ $name }}">{{ old($name, $value) }}</textarea>
    {{ $slot }}
    @if ($hint)<span class="hint">{{ $hint }}</span>@endif
    @error($name)<span class="error">{{ $message }}</span>@enderror
</div>
