@props(['label', 'value', 'unit' => null, 'delta' => null, 'trend' => null, 'spark' => null, 'color' => 'var(--color-water)'])

{{-- spark : valeurs de la sparkline, dessinée par back.js à partir de data-spark ; trend : up (rouge) ou down (vert) --}}
<div {{ $attributes->class(['card flex flex-col gap-2 px-[18px] py-4']) }} @if ($spark) data-spark="{{ implode(',', $spark) }}" data-color="{{ $color }}" @endif>
    <span class="font-mono text-[.7rem] tracking-[.08em] text-muted uppercase">{{ $label }}</span>
    <span class="font-display text-[1.75rem] leading-none font-extrabold tabular-nums">{{ $value }}@if ($unit) <small class="text-[.9rem]">{{ $unit }}</small>@endif</span>
    @if ($delta)<span @class(['text-[.78rem]', 'text-crit-fg' => $trend === 'up', 'text-ok-fg' => $trend === 'down'])>{{ $delta }}</span>@endif
    {{ $slot }}
</div>
