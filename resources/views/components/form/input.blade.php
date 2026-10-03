@props(['name', 'label', 'type' => 'text', 'value' => null, 'id' => null, 'hint' => null])

@php
    $id ??= $name;
    $field = str_replace(['[', ']'], ['.', ''], $name);
@endphp

<div @class(['field', 'is-invalid' => $errors->has($field)])>
    @isset($aside)
        <div class="row" style="justify-content:space-between"><label for="{{ $id }}">{{ $label }}</label>{{ $aside }}</div>
    @else
        <label for="{{ $id }}">{{ $label }}</label>
    @endisset
    <input {{ $attributes->class(['input']) }} type="{{ $type }}" id="{{ $id }}" name="{{ $name }}" @if ($type !== 'password') value="{{ old($field, $value) }}" @endif>
    {{ $slot }}
    @if ($hint)<span class="hint">{{ $hint }}</span>@endif
    @error($field)<span class="error">{{ $message }}</span>@enderror
</div>
