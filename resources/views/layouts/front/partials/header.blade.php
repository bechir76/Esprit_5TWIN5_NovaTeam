<header class="topbar">
  <div class="wrap">
    <a class="brand" href="{{ route('home') }}" aria-label="AquaSecure, accueil">
      <svg width="30" height="30" viewBox="0 0 32 32" aria-hidden="true"><path d="M16 3C16 3 6 14.5 6 20.5a10 10 0 0 0 20 0C26 14.5 16 3 16 3Z" fill="#1CA7EC"/><path d="M11 21.5c1.8 1.6 3.4 1.6 5 0s3.2-1.6 5 0" stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round"/></svg>
      AquaSecure
    </a>
    <button class="burger" id="burger" aria-label="Ouvrir le menu">☰</button>
    <nav class="nav" id="nav">
      <a href="{{ route('home') }}" @class(['active' => request()->routeIs('home')])>Accueil</a>
      <a href="{{ route('map') }}" @class(['active' => request()->routeIs('map')])>Carte du réseau</a>
      <a href="{{ route('water-quality') }}" @class(['active' => request()->routeIs('water-quality')])>Qualité de l'eau</a>
      <a href="{{ route('incidents.create') }}" @class(['active' => request()->routeIs('incidents.create')])>Signaler un incident</a>
      <a href="{{ route('incidents.index') }}" @class(['active' => request()->routeIs('incidents.index')])>Incidents</a>
      <a href="{{ route('projects.index') }}" @class(['active' => request()->routeIs('projects.*')])>Projets</a>
    </nav>
    @guest
    <a class="btn btn-ghost btn-sm" href="{{ route('login') }}" id="guestBtn">Connexion</a>
    @else
    <div class="user-menu">
      <button class="avatar" id="avatarBtn" aria-haspopup="true" aria-expanded="false">{{ auth()->user()->initials() }}</button>
      <div class="dropdown" id="userDrop" hidden>
        <div style="padding:10px 12px"><strong>{{ auth()->user()->name }}</strong><div class="muted" style="font-size:.82rem">{{ auth()->user()->role->label() }}@if (auth()->user()->organization) · {{ auth()->user()->organization }}@endif</div></div>
        <hr>
        <a href="{{ route('profile.edit') }}">Mon profil</a>
        <a href="{{ route('profile.edit') }}">Mes signalements</a>
        <a href="{{ route('profile.edit') }}">Mes contributions</a>
        @if (auth()->user()->isStaff())
        <a href="{{ route('admin.dashboard') }}">Console de gestion</a>
        @endif
        <hr>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <a href="{{ route('logout') }}" onclick="event.preventDefault(); this.closest('form').submit();">Se déconnecter</a>
        </form>
      </div>
    </div>
    @endguest
  </div>
</header>
