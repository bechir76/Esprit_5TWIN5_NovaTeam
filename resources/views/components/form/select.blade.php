@props(['name', 'label', 'options' => [], 'value' => null, 'id' => null, 'hint' => null])

@php
    $id ??= $name;
    $selected = old($name, $value);
    $invalid = $errors->has($name);
    // Liste ['Bon', 'Critique'] : valeur = libellé ; tableau associatif (y compris id => nom) : valeur = clé
    $isList = array_is_list($options);
@endphp

<div class="flex flex-col gap-1.5">
    <label class="text-[.86rem] font-semibold" for="{{ $id }}">{{ $label }}</label>
    <select {{ $attributes->class(['input', 'border-crit! bg-crit-soft!' => $invalid]) }} id="{{ $id }}" name="{{ $name }}" @if ($invalid) aria-invalid="true" @endif>
        @foreach ($options as $key => $text)
            @php($optionValue = $isList ? $text : $key)
            <option value="{{ $optionValue }}" @selected((string) $selected === (string) $optionValue)>{{ $text }}</option>
        @endforeach
    </select>
    {{ $slot }}
    @if ($hint)<span class="text-[.8rem] text-muted">{{ $hint }}</span>@endif
    @error($name)<span class="text-[.8rem] font-medium text-crit-fg">{{ $message }}</span>@enderror
</div>
