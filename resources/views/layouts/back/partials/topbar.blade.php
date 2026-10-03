    <header class="topbar">
      <button class="icon-btn burger" id="burger" aria-label="Menu">☰</button>
      <div class="search"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input placeholder="Rechercher un ouvrage, un incident, un utilisateur…" aria-label="Recherche"></div>
      <div class="row" style="margin-left:auto;gap:10px;flex-wrap:nowrap">
        <div style="position:relative">
          <button class="icon-btn" id="notifBtn" aria-label="Notifications"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/></svg><span class="pip"></span></button>
          <div class="dropdown" id="notifDrop" hidden style="width:320px;max-width:calc(100vw - 32px)">
            <div class="row between" style="padding:8px 12px"><strong>Alertes</strong><span class="badge b-crit">3 critiques</span></div>
            <div class="notif-item"><span class="badge b-crit dot"></span><div><strong>Nitrate 62 mg/L</strong> au forage de Mornag F3<div class="muted">il y a 12 min</div></div></div>
            <div class="notif-item"><span class="badge b-crit dot"></span><div><strong>Chute de pression</strong> secteur Ariana Nord (1,2 bar)<div class="muted">il y a 40 min</div></div></div>
            <div class="notif-item"><span class="badge b-warn dot"></span><div><strong>5 signalements groupés</strong> rue Ibn Sina<div class="muted">il y a 2 h</div></div></div>
          </div>
        </div>
        <button class="me" id="meBtn" aria-haspopup="true"><span class="av" style="background:var(--navy-2)">KT</span><span class="who" style="text-align:left;line-height:1.2"><strong style="font-size:.86rem">Karim Trabelsi</strong><br><span class="muted" style="font-size:.76rem">Administrateur</span></span></button>
        <div style="position:relative"><div class="dropdown" id="meDrop" hidden style="top:28px">
          <a href="{{ route('admin.profile.edit') }}">Mon profil</a><a href="{{ route('admin.settings.edit') }}">Paramètres</a>
          <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <a href="{{ route('admin.logout') }}" onclick="event.preventDefault(); this.closest('form').submit();">Se déconnecter</a>
          </form></div></div>
      </div>
    </header>
