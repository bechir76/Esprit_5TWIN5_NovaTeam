@extends('layouts.front.app')

@section('title', 'Accueil')

@section('content')
<section data-page="accueil">
  <div class="hero">
    <canvas id="ripple" aria-hidden="true"></canvas>
    <div class="wrap">
      <div>
        <span class="eyebrow" style="color:#7FD8FF">Réseau d'eau potable · Grand Tunis</span>
        <h1 style="margin-top:14px">Une eau sûre se surveille ensemble.</h1>
        <p class="lead">Signalez une fuite, une eau trouble ou une coupure en deux minutes. Suivez l'état des réservoirs et des canalisations de votre quartier, et financez les travaux qui comptent.</p>
        <div class="row">
          <a class="btn btn-primary" href="{{ route('incidents.create') }}">Signaler un incident</a>
          <a class="btn btn-outline-light" href="{{ route('map') }}">Voir la carte du réseau</a>
        </div>
      </div>
      <div class="gauge-card" aria-label="Qualité de l'eau mesurée aujourd'hui">
        <div class="row" style="justify-content:space-between;margin-bottom:6px">
          <strong>Qualité mesurée aujourd'hui</strong><x-status-badge value="Conforme" style="background:rgba(46,196,182,.18);color:#7BE8DC" />
        </div>
        <div class="muted" style="color:#9CBCD1;font-size:.82rem;margin-bottom:8px">Station de traitement Ghdir El Golla · relevé 08:30</div>
        <div class="k"><span>Chlore résiduel</span><span class="v">0,42 mg/L</span></div>
        <div class="k"><span>Turbidité</span><span class="v">0,6 NTU</span></div>
        <div class="k"><span>pH</span><span class="v">7,4</span></div>
        <div class="k"><span>Pression moyenne du réseau</span><span class="v">3,1 bar</span></div>
        <div class="k"><span>Niveau des barrages</span><span class="v" style="color:#F4C18F">31 %</span></div>
      </div>
    </div>
  </div>
  <div class="wrap">
    <div class="stats">
      <div><strong class="mono">1 284</strong><span class="muted">incidents résolus en 2026</span></div>
      <div><strong class="mono">36</strong><span class="muted">projets de rénovation financés</span></div>
      <div><strong class="mono">512</strong><span class="muted">infrastructures surveillées</span></div>
      <div><strong class="mono">18 h</strong><span class="muted">délai moyen d'intervention</span></div>
    </div>

    <div class="grid g2" style="margin-top:64px;align-items:start">
      <div class="stack" style="gap:20px">
        <span class="eyebrow">Comment ça marche</span>
        <h2>Du signalement à la réparation</h2>
        <div class="stack steps" style="gap:22px;margin-top:8px">
          <div class="step"><div><h3>Vous signalez</h3><p class="muted">Photo, position et description. L'assistant IA propose la catégorie et la gravité.</p></div></div>
          <div class="step"><div><h3>La régie qualifie et intervient</h3><p class="muted">Le signalement est vérifié, rattaché à l'infrastructure concernée et confié à une équipe terrain.</p></div></div>
          <div class="step"><div><h3>Vous suivez jusqu'à la résolution</h3><p class="muted">Chaque changement de statut vous est notifié, jusqu'à la clôture.</p></div></div>
        </div>
      </div>
      <div class="card pad">
        <div class="row" style="justify-content:space-between;margin-bottom:6px"><h3>Derniers signalements</h3><a href="{{ route('incidents.index') }}" class="muted" style="font-size:.88rem">Tout voir</a></div>
        <div id="homeIncidents"></div>
      </div>
    </div>

    <div style="margin-top:64px">
      <div class="screen-head"><div class="stack" style="gap:8px"><span class="eyebrow">Financement participatif</span><h2>Projets en cours de financement</h2></div><a class="btn btn-ghost" href="{{ route('projects.index') }}">Tous les projets</a></div>
      <div class="grid g3" id="homeProjects"></div>
    </div>
  </div>
</section>
@endsection
