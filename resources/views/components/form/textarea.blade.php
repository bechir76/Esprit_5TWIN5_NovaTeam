@props(['name', 'label', 'value' => null, 'id' => null, 'hint' => null])

@php
    $id ??= $name;
    $invalid = $errors->has($name);
@endphp

<div class="flex flex-col gap-1.5">
    <label class="text-[.86rem] font-semibold" for="{{ $id }}">{{ $label }}</label>
    <textarea {{ $attributes->class(['input', 'border-crit! bg-crit-soft!' => $invalid]) }} id="{{ $id }}" name="{{ $name }}" @if ($invalid) aria-invalid="true" @endif>{{ old($name, $value) }}</textarea>
    {{ $slot }}
    @if ($hint)<span class="text-[.8rem] text-muted">{{ $hint }}</span>@endif
    @error($name)<span class="text-[.8rem] font-medium text-crit-fg">{{ $message }}</span>@enderror
</div>
