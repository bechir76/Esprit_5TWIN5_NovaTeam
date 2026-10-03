@props(['name', 'label', 'options' => [], 'value' => null, 'id' => null, 'hint' => null])

@php
    $id ??= $name;
    $selected = old($name, $value);
@endphp

<div @class(['field', 'is-invalid' => $errors->has($name)])>
    <label for="{{ $id }}">{{ $label }}</label>
    <select {{ $attributes->class(['input']) }} id="{{ $id }}" name="{{ $name }}">
        @foreach ($options as $key => $text)
            @php($optionValue = is_int($key) ? $text : $key)
            <option value="{{ $optionValue }}" @selected((string) $selected === (string) $optionValue)>{{ $text }}</option>
        @endforeach
    </select>
    {{ $slot }}
    @if ($hint)<span class="hint">{{ $hint }}</span>@endif
    @error($name)<span class="error">{{ $message }}</span>@enderror
</div>
