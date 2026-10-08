@props(['title', 'crumb' => null])

{{-- En-tête des pages du back : fil d'Ariane, titre, badges optionnels (slot badges) et boutons d'action (slot par défaut) --}}
<div {{ $attributes->class(['flex flex-wrap items-end justify-between gap-3.5']) }}>
    <div>
        <div class="mb-1 text-[.8rem] text-muted">{{ $crumb ?? 'Console / '.$title }}</div>
        @isset($badges)
            <div class="flex flex-wrap items-center gap-2.5"><h1>{{ $title }}</h1>{{ $badges }}</div>
        @else
            <h1>{{ $title }}</h1>
        @endisset
    </div>
    @if ($slot->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2.5">{{ $slot }}</div>
    @endif
</div>
