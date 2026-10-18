  <aside class="fixed inset-y-0 left-0 z-40 flex w-[260px] flex-col overflow-y-auto bg-navy px-3.5 py-[18px] text-[#BFD5E4] transition-transform duration-200 max-[980px]:-translate-x-full min-[981px]:sticky min-[981px]:top-0 min-[981px]:bottom-auto min-[981px]:h-screen min-[981px]:w-auto"
         :class="{ 'max-[980px]:translate-x-0!': sidebar }">
    <a class="flex items-center gap-2.5 px-2 pt-1 pb-[18px] font-display text-[1.1rem] font-extrabold text-white" href="{{ route('admin.dashboard') }}">
      <svg class="flex-none" width="30" height="30" viewBox="0 0 32 32" aria-hidden="true"><path d="M16 3C16 3 6 14.5 6 20.5a10 10 0 0 0 20 0C26 14.5 16 3 16 3Z" fill="#1CA7EC"/><path d="M11 21.5c1.8 1.6 3.4 1.6 5 0s3.2-1.6 5 0" stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round"/></svg>
      <span>AquaSecure<small class="block font-mono text-[.65rem] font-normal tracking-[.1em] text-[#7FB3D3]">CONSOLE DE GESTION</small></span>
    </a>
    @php
        // Menu de la console : [route, libellé, icône, compteur, motif routeIs pour le lien actif, Gate requise]
        $icons = [
            'dash' => '<path d="M3 13h8V3H3zM13 21h8V11h-8zM3 21h8v-6H3zM13 3v6h8V3z"/>',
            'zone' => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/>',
            'infra' => '<path d="M4 20V9l8-5 8 5v11"/><path d="M9 20v-6h6v6"/>',
            'water' => '<path d="M12 2C12 2 5 10.5 5 15a7 7 0 0 0 14 0C19 10.5 12 2 12 2Z"/><path d="m9.5 14 1.5 1.5 3.5-4"/>',
            'inc' => '<path d="M12 3 2 20h20Z"/><path d="M12 10v4M12 17h.01"/>',
            'tool' => '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4Z"/>',
            'proj' => '<path d="M3 7h18v13H3zM8 7V4h8v3"/>',
            'money' => '<circle cx="12" cy="12" r="9"/><path d="M15 9h-4a2 2 0 0 0 0 4h2a2 2 0 0 1 0 4H9M12 6v2M12 16v2"/>',
            'ai' => '<path d="M12 3v3M12 18v3M3 12h3M18 12h3M6 6l2 2M16 16l2 2M6 18l2-2M16 8l2-2"/><circle cx="12" cy="12" r="3"/>',
            'users' => '<circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0M17 11a3 3 0 1 0 0-6M22 21a6 6 0 0 0-4-5.7"/>',
            'shield' => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6Z"/><path d="m9 12 2 2 4-4"/>',
            'cog' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
        ];
        $menu = [
            'Pilotage' => [
                ['admin.dashboard', 'Tableau de bord', 'dash', null, 'admin.dashboard'],
                ['admin.zones.index', 'Zones', 'zone', null, 'admin.zones.*'],
                ['admin.infrastructures.index', 'Infrastructures', 'infra', null, 'admin.infrastructures.*'],
                ['admin.incidents.index', 'Incidents', 'inc', \App\Models\Incident::open()->count(), 'admin.incidents.index|admin.incidents.create|admin.incidents.show|admin.incidents.edit'],
                ['admin.incidents.map', 'Carte des incidents', 'zone', null, 'admin.incidents.map'],
                ['admin.incidents.stats', 'Statistiques incidents', 'dash', null, 'admin.incidents.stats'],
                ['admin.interventions.index', 'Interventions', 'tool', null, 'admin.interventions.index|admin.interventions.create|admin.interventions.show|admin.interventions.edit'],
                ['admin.interventions.planning', 'Planning', 'tool', null, 'admin.interventions.planning'],
                ['admin.water-analyses.index', 'Qualité de l\'eau', 'water', null, 'admin.water-analyses.*|admin.measurements.*'],
            ],
            'Financement' => [
                ['admin.projects.index', 'Projets de rénovation', 'proj', null, 'admin.projects.*'],
                ['admin.fundings.index', 'Financements', 'money', null, 'admin.fundings.index|admin.fundings.create|admin.fundings.show|admin.fundings.edit'],
                ['admin.fundings.dashboard', 'Tableau financier', 'dash', null, 'admin.fundings.dashboard'],
            ],
            'Intelligence' => [
                ['admin.ai.insights', 'Analyses IA', 'ai', null, 'admin.ai.*'],
            ],
            'Administration' => [
                ['admin.users.index', 'Utilisateurs', 'users', null, 'admin.users.*', 'manage-users'],
                ['admin.roles.index', 'Rôles et permissions', 'shield', null, 'admin.roles.*', 'manage-users'],
                ['admin.settings.edit', 'Paramètres', 'cog', null, 'admin.settings.*'],
            ],
        ];
    @endphp
    <nav>
      @foreach ($menu as $group => $items)
      <div class="mt-3.5">
        <div class="px-2.5 pb-1.5 font-mono text-[.7rem] tracking-[.08em] text-[#6E9DBC] uppercase">{{ $group }}</div>
        @foreach ($items as $item)
        @php([$route, $label, $icon, $count, $pattern] = $item)
        @continue(isset($item[5]) && auth()->user()->cannot($item[5]))
        <a href="{{ route($route) }}" @class([
            'flex items-center gap-[11px] rounded-lg px-2.5 py-[9px] text-[.9rem] font-medium hover:bg-white/6 hover:text-white',
            'bg-water/18 text-white shadow-[inset_3px_0_0_var(--color-water)]' => request()->routeIs($pattern),
        ])><svg class="flex-none opacity-80" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$icon] !!}</svg>{{ $label }}@if ($count)<span class="ml-auto rounded-full bg-crit px-[7px] py-px font-mono text-[.72rem] text-white">{{ $count }}</span>@endif</a>
        @endforeach
      </div>
      @endforeach
    </nav>
    @php($me = auth()->user())
    <div class="mt-auto border-t border-white/10 px-2.5 pt-3.5 pb-1 text-[.78rem] text-[#7FA6C0]">
      <div class="mb-2.5 flex items-center gap-2.5">
        <span class="grid size-9 flex-none place-items-center rounded-full text-[.8rem] font-bold text-white" style="background:{{ $me->role->color() }}"><x-avatar :user="$me" /></span>
        <span class="min-w-0 leading-tight"><strong class="text-[.84rem] text-white">{{ $me->name }}</strong><br>{{ $me->role->label() }}</span>
      </div>
      {{ $me->organization ?? 'AquaSecure' }}<br><span class="font-mono">v1.0 · Laravel 12</span>
    </div>
  </aside>
