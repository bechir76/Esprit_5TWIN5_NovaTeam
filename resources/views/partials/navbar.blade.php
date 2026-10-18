{{-- Barre du haut : topbar de la console ($admin) ou en-tête du site citoyen --}}
@if ($admin)
    @php $me = auth()->user(); @endphp
    <header class="sticky top-[env(safe-area-inset-top,0px)] z-[15] flex h-[62px] items-center gap-3.5 border-b border-line bg-surface px-4 min-[981px]:px-6">
      <button class="grid size-[38px] place-items-center rounded-[9px] border border-line bg-surface text-ink min-[981px]:hidden" type="button" @click="sidebar = ! sidebar" aria-label="Menu">☰</button>

      <div class="relative max-w-[440px] min-w-0 flex-1">
        <svg class="absolute top-1/2 left-[11px] -translate-y-1/2 text-muted" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input class="w-full rounded-[9px] border border-line bg-surface-2 py-[9px] pr-3 pl-9 text-ink" placeholder="Rechercher un ouvrage, un incident, un utilisateur…" aria-label="Recherche">
      </div>

      <div class="ml-auto flex items-center gap-2.5">
        {{-- Notifications (module incidents : cloche partagée console / site citoyen) --}}
        @include('partials.notifications-bell')

        <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
          <button class="relative grid size-[38px] place-items-center rounded-[9px] border border-line bg-surface text-ink" type="button" @click="open = ! open" :aria-expanded="open" aria-label="Notifications">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/></svg>
            <span class="absolute top-[7px] right-2 size-2 rounded-full border-2 border-surface bg-crit"></span>
          </button>
          <div class="absolute top-[50px] right-0 z-30 w-[320px] max-w-[calc(100vw-32px)] rounded-card border border-line bg-surface p-1.5 shadow-drop" x-show="open" x-cloak x-transition.opacity>
            <div class="flex items-center justify-between px-3 py-2"><strong>Alertes</strong><span class="badge b-crit">3 critiques</span></div>
            @foreach ([
                ['b-crit', 'Nitrate 62 mg/L', 'au forage de Mornag F3', 'il y a 12 min'],
                ['b-crit', 'Chute de pression', 'secteur Ariana Nord (1,2 bar)', 'il y a 40 min'],
                ['b-warn', '5 signalements groupés', 'rue Ibn Sina', 'il y a 2 h'],
            ] as [$badge, $title, $text, $when])
            <div class="flex gap-2.5 rounded-md px-3 py-2.5 text-[.85rem] hover:bg-surface-2"><span class="badge dot {{ $badge }} self-start"></span><div><strong>{{ $title }}</strong> {{ $text }}<div class="text-muted">{{ $when }}</div></div></div>
            @endforeach
          </div>
        </div>

        <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
          <button class="flex items-center gap-2.5 rounded-[9px] p-1 text-ink" type="button" @click="open = ! open" aria-haspopup="true" :aria-expanded="open">
            <span class="grid size-9 flex-none place-items-center rounded-full text-[.8rem] font-bold text-white" style="background:{{ $me->role->color() }}"><x-avatar :user="$me" /></span>
            <span class="hidden text-left leading-tight min-[981px]:block"><strong class="text-[.86rem]">{{ $me->name }}</strong><br><span class="text-[.76rem] text-muted">{{ $me->role->label() }}</span></span>
          </button>
          <div class="absolute top-[50px] right-0 z-30 min-w-[210px] rounded-card border border-line bg-surface p-1.5 shadow-drop" x-show="open" x-cloak x-transition.opacity>
            <a class="menu-item" href="{{ route('admin.profile.edit') }}">Mon profil</a>
            <a class="menu-item" href="{{ route('admin.settings.edit') }}">Paramètres</a>
            <a class="menu-item" href="{{ route('home') }}">Espace citoyen</a>
            <form method="POST" action="{{ route('admin.logout') }}">
              @csrf
              <button class="menu-item w-full" type="submit">Se déconnecter</button>
            </form>
          </div>
        </div>
      </div>
    </header>
@else
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
    @php $me = auth()->user(); @endphp
    @include('partials.notifications-bell')
    <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
      <button class="grid size-9 place-items-center rounded-full bg-teal text-[.85rem] font-bold text-[#06302C]" type="button" @click="open = ! open" aria-haspopup="true" :aria-expanded="open"><x-avatar :user="$me" /></button>
      <div class="absolute right-0 top-[46px] min-w-[200px] rounded-card border border-line bg-surface p-1.5 shadow-card" x-show="open" x-cloak x-transition.opacity>
        <div class="px-3 py-2.5"><strong>{{ $me->name }}</strong><div class="text-[.82rem] text-muted">{{ $me->role->label() }}@if ($me->organization) · {{ $me->organization }}@endif</div></div>
        <hr class="my-1.5 border-line">
        <a class="menu-item" href="{{ route('profile.edit') }}">Mon profil</a>
        <a class="menu-item" href="{{ route('profile.edit') }}#signalements">Mes signalements</a>
        <a class="menu-item" href="{{ route('profile.edit') }}#contributions">Mes contributions</a>
        <a class="menu-item" href="{{ route('notifications.index') }}">Mes notifications @if ($me->unreadNotifications()->exists())<span class="badge b-info">nouveau</span>@endif</a>
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
@endif
