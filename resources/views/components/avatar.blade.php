@props(['user'])

{{-- Contenu d'un avatar : photo de profil si elle existe, sinon initiales (le cercle coloré est l'élément parent) --}}
@if ($user->avatarUrl())<img class="size-full rounded-full object-cover" src="{{ $user->avatarUrl() }}" alt="Photo de {{ $user->name }}">@else{{ $user->initials() }}@endif
