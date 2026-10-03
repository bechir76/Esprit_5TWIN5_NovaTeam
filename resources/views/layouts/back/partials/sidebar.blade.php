  <aside class="sidebar" id="sidebar">
    <a class="brand" href="{{ route('admin.dashboard') }}"><svg width="30" height="30" viewBox="0 0 32 32"><path d="M16 3C16 3 6 14.5 6 20.5a10 10 0 0 0 20 0C26 14.5 16 3 16 3Z" fill="#1CA7EC"/><path d="M11 21.5c1.8 1.6 3.4 1.6 5 0s3.2-1.6 5 0" stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round"/></svg><span>AquaSecure<small>CONSOLE DE GESTION</small></span></a>
    @php
        // Menu de la console : [route, libellé, icône, compteur, motif routeIs pour le lien actif]
        $icons = [
            'dash' => '<path d="M3 13h8V3H3zM13 21h8V11h-8zM3 21h8v-6H3zM13 3v6h8V3z"/>',
            'infra' => '<path d="M4 20V9l8-5 8 5v11"/><path d="M9 20v-6h6v6"/>',
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
                ['admin.infrastructures.index', 'Infrastructures', 'infra', null, 'admin.infrastructures.*'],
                ['admin.incidents.index', 'Incidents', 'inc', 7, 'admin.incidents.*'],
                ['admin.interventions.index', 'Interventions', 'tool', null, 'admin.interventions.*'],
            ],
            'Financement' => [
                ['admin.projects.index', 'Projets de rénovation', 'proj', null, 'admin.projects.*'],
                ['admin.fundings.index', 'Financements', 'money', null, 'admin.fundings.*'],
            ],
            'Intelligence' => [
                ['admin.ai.insights', 'Analyses IA', 'ai', null, 'admin.ai.*'],
            ],
            'Administration' => [
                ['admin.users.index', 'Utilisateurs', 'users', null, 'admin.users.*'],
                ['admin.roles.index', 'Rôles et permissions', 'shield', null, 'admin.roles.*'],
                ['admin.settings.edit', 'Paramètres', 'cog', null, 'admin.settings.*'],
            ],
        ];
    @endphp
    <nav class="side" id="side">
      @foreach ($menu as $group => $items)
      <div class="side-group"><div class="label">{{ $group }}</div>
        @foreach ($items as [$route, $label, $icon, $count, $pattern])
        <a href="{{ route($route) }}" @class(['active' => request()->routeIs($pattern)])><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$icon] !!}</svg>{{ $label }}@if ($count)<span class="count">{{ $count }}</span>@endif</a>
        @endforeach
      </div>
      @endforeach
    </nav>
    <div class="side-foot">Régie des eaux d'Ariana<br><span class="mono">v1.0 · Laravel 12</span></div>
  </aside>
