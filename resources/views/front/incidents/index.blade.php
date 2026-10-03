@extends('layouts.front.app')

@section('title', 'Incidents')

@section('content')
<section class="screen" data-page="incidents">
  <div class="wrap">
    <div class="screen-head"><div class="stack" style="gap:8px"><span class="eyebrow">Suivi public</span><h2>Incidents signalés</h2></div><a class="btn btn-primary" href="{{ route('incidents.create') }}">Signaler un incident</a></div>
    <div class="tabs"><a href="{{ route('incidents.index') }}" class="on">Tous <span class="mono muted">(248)</span></a><a href="{{ route('incidents.index') }}">Signalés</a><a href="{{ route('incidents.index') }}">En cours</a><a href="{{ route('incidents.index') }}">Résolus</a></div>
    <div class="toolbar">
      <input class="input" placeholder="Rechercher un incident, une rue…" aria-label="Rechercher">
      <select class="input" aria-label="Type"><option>Tous les types</option><option>Fuite</option><option>Contamination</option><option>Coupure</option></select>
      <select class="input" aria-label="Zone"><option>Toutes les zones</option><option>Tunis</option><option>Ariana</option><option>Ben Arous</option></select>
    </div>
    <div class="table-wrap"><table><thead><tr><th>Référence</th><th>Incident</th><th>Type</th><th>Zone</th><th>Gravité</th><th>Statut</th><th>Signalé le</th></tr></thead><tbody id="incTable"></tbody></table></div>
    <div class="pager"><a href="{{ route('incidents.index') }}">‹</a><a class="on" href="{{ route('incidents.index') }}">1</a><a href="{{ route('incidents.index') }}">2</a><a href="{{ route('incidents.index') }}">3</a><a href="{{ route('incidents.index') }}">›</a></div>
  </div>
</section>
@endsection
