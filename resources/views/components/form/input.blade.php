@props(['name', 'label', 'type' => 'text', 'value' => null, 'id' => null, 'hint' => null])

@php
    $id ??= $name;
    $field = str_replace(['[', ']'], ['.', ''], $name);
    $invalid = $errors->has($field);
@endphp

<div class="flex flex-col gap-1.5">
    @isset($aside)
        <div class="flex items-center justify-between gap-3"><label class="text-[.86rem] font-semibold" for="{{ $id }}">{{ $label }}</label>{{ $aside }}</div>
    @else
        <label class="text-[.86rem] font-semibold" for="{{ $id }}">{{ $label }}</label>
    @endisset
    <input {{ $attributes->class(['input', 'border-crit! bg-crit-soft!' => $invalid]) }} type="{{ $type }}" id="{{ $id }}" name="{{ $name }}" @if ($type !== 'password') value="{{ old($field, $value) }}" @endif @if ($invalid) aria-invalid="true" @endif>
    {{ $slot }}
    @if ($hint)<span class="text-[.8rem] text-muted">{{ $hint }}</span>@endif
    @error($field)<span class="text-[.8rem] font-medium text-crit-fg">{{ $message }}</span>@enderror
</div>
