@php
    $links = [
        ['home', 'Accueil', 'home'],
        ['map', 'Carte du réseau', 'map'],
        ['water-quality', 'Qualité de l\'eau', 'water-quality'],
        ['incidents.create', 'Signaler un incident', 'incidents.create'],
        ['incidents.index', 'Incidents', 'incidents.index'],
        ['projects.index', 'Projets', 'projects.*'],
    ];
@endphp

<header class="sticky top-[env(safe-area-inset-top,0px)] z-20 border-b border-line bg-surface" x-data="{ nav: false }">
  <div class="relative mx-auto flex h-[66px] max-w-[1200px] items-center gap-5 px-5">
    <a class="flex items-center gap-2.5 font-display text-[1.15rem] font-extrabold text-ink" href="{{ route('home') }}" aria-label="AquaSecure, accueil">
      <svg class="flex-none" width="30" height="30" viewBox="0 0 32 32" aria-hidden="true"><path d="M16 3C16 3 6 14.5 6 20.5a10 10 0 0 0 20 0C26 14.5 16 3 16 3Z" fill="#1CA7EC"/><path d="M11 21.5c1.8 1.6 3.4 1.6 5 0s3.2-1.6 5 0" stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round"/></svg>
      AquaSecure
    </a>

    <button class="ml-auto rounded-lg border border-line px-2.5 py-2 text-ink min-[901px]:hidden" type="button" @click="nav = ! nav" :aria-expanded="nav" aria-label="Ouvrir le menu">☰</button>

    <nav class="absolute inset-x-0 top-[66px] hidden flex-col gap-1 border-b border-line bg-surface px-5 py-2.5 min-[901px]:static min-[901px]:ml-auto min-[901px]:flex min-[901px]:flex-row min-[901px]:border-0 min-[901px]:bg-transparent min-[901px]:p-0"
         :class="{ 'flex': nav, 'hidden': ! nav }">
      @foreach ($links as [$route, $label, $pattern])
      <a href="{{ route($route) }}" @class([
          'rounded-lg px-3 py-2 font-medium',
          'bg-water-soft text-navy dark:text-water' => request()->routeIs($pattern),
          'text-muted hover:bg-surface-2 hover:text-ink' => ! request()->routeIs($pattern),
      ])>{{ $label }}</a>
      @endforeach
    </nav>

    @guest
    <a class="btn btn-ghost btn-sm" href="{{ route('login') }}">Connexion</a>
    @else
    @php($me = auth()->user())
    <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
      <button class="grid size-9 place-items-center rounded-full bg-teal text-[.85rem] font-bold text-[#06302C]" type="button" @click="open = ! open" aria-haspopup="true" :aria-expanded="open">{{ $me->initials() }}</button>
      <div class="absolute right-0 top-[46px] min-w-[200px] rounded-card border border-line bg-surface p-1.5 shadow-card" x-show="open" x-cloak x-transition.opacity>
        <div class="px-3 py-2.5"><strong>{{ $me->name }}</strong><div class="text-[.82rem] text-muted">{{ $me->role->label() }}@if ($me->organization) · {{ $me->organization }}@endif</div></div>
        <hr class="my-1.5 border-line">
        <a class="menu-item" href="{{ route('profile.edit') }}">Mon profil</a>
        <a class="menu-item" href="{{ route('profile.edit') }}#signalements">Mes signalements</a>
        <a class="menu-item" href="{{ route('profile.edit') }}#contributions">Mes contributions</a>
        @if ($me->isStaff())
        <a class="menu-item" href="{{ route('admin.dashboard') }}">Console de gestion</a>
        @endif
        <hr class="my-1.5 border-line">
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button class="menu-item w-full" type="submit">Se déconnecter</button>
        </form>
      </div>
    </div>
    @endguest
  </div>
</header>
