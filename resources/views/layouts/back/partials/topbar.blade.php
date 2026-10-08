    @php($me = auth()->user())
    <header class="sticky top-[env(safe-area-inset-top,0px)] z-[15] flex h-[62px] items-center gap-3.5 border-b border-line bg-surface px-4 min-[981px]:px-6">
      <button class="grid size-[38px] place-items-center rounded-[9px] border border-line bg-surface text-ink min-[981px]:hidden" type="button" @click="sidebar = ! sidebar" aria-label="Menu">☰</button>

      <div class="relative max-w-[440px] min-w-0 flex-1">
        <svg class="absolute top-1/2 left-[11px] -translate-y-1/2 text-muted" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input class="w-full rounded-[9px] border border-line bg-surface-2 py-[9px] pr-3 pl-9 text-ink" placeholder="Rechercher un ouvrage, un incident, un utilisateur…" aria-label="Recherche">
      </div>

      <div class="ml-auto flex items-center gap-2.5">
        {{-- Alertes (données de démonstration) --}}
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
            <span class="grid size-9 flex-none place-items-center rounded-full text-[.8rem] font-bold text-white" style="background:{{ $me->role->color() }}">{{ $me->initials() }}</span>
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
