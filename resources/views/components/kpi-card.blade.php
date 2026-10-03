@props(['label', 'value', 'unit' => null, 'delta' => null, 'trend' => null, 'spark' => null, 'color' => 'var(--water)'])

{{-- spark : valeurs de la sparkline, dessinée par back.js à partir de data-spark --}}
<div {{ $attributes->class(['card', 'kpi']) }} @if ($spark) data-spark="{{ implode(',', $spark) }}" data-color="{{ $color }}" @endif>
    <span class="label">{{ $label }}</span>
    <span class="v">{{ $value }}@if ($unit) <small style="font-size:.9rem">{{ $unit }}</small>@endif</span>
    @if ($delta)<span @class(['d', $trend])>{{ $delta }}</span>@endif
    {{ $slot }}
</div>
