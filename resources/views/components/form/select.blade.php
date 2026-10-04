@props(['name', 'label', 'options' => [], 'value' => null, 'id' => null, 'hint' => null])

@php
    $id ??= $name;
    $selected = old($name, $value);
    // Liste ['Bon', 'Critique'] : valeur = libellé ; tableau associatif (y compris id => nom) : valeur = clé
    $isList = array_is_list($options);
@endphp

<div @class(['field', 'is-invalid' => $errors->has($name)])>
    <label for="{{ $id }}">{{ $label }}</label>
    <select {{ $attributes->class(['input']) }} id="{{ $id }}" name="{{ $name }}">
        @foreach ($options as $key => $text)
            @php($optionValue = $isList ? $text : $key)
            <option value="{{ $optionValue }}" @selected((string) $selected === (string) $optionValue)>{{ $text }}</option>
        @endforeach
    </select>
    {{ $slot }}
    @if ($hint)<span class="hint">{{ $hint }}</span>@endif
    @error($name)<span class="error">{{ $message }}</span>@enderror
</div>
