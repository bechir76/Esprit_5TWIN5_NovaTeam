@props(['title', 'crumb' => null])

{{-- En-tête des pages du back : fil d'Ariane, titre, badges optionnels (slot badges) et boutons d'action (slot par défaut) --}}
<div {{ $attributes->class(['page-head']) }}>
    <div>
        <div class="crumbs">{{ $crumb ?? 'Console / '.$title }}</div>
        @isset($badges)
            <div class="row"><h1>{{ $title }}</h1>{{ $badges }}</div>
        @else
            <h1>{{ $title }}</h1>
        @endisset
    </div>
    {{ $slot }}
</div>
