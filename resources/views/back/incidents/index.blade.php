@extends('layouts.back.app')

@section('title', 'Incidents')

@section('content')
<section data-page="incidents" class="stack">
  <x-page-header title="Incidents">
    <div class="row"><button class="btn btn-ghost" type="button">Vue carte</button><button class="btn btn-primary" type="button">+ Créer un incident</button></div>
  </x-page-header>
  <div class="card">
    <div class="filters"><input class="input grow" placeholder="Référence, adresse, déclarant…" aria-label="Rechercher"><select class="input" aria-label="Type"><option>Tous les types</option><option>Fuite</option><option>Contamination</option><option>Coupure</option></select><select class="input" aria-label="Gravité"><option>Toutes gravités</option><option>Critique</option><option>Élevée</option><option>Moyenne</option><option>Faible</option></select><select class="input" aria-label="Statut"><option>Tous les statuts</option><option>Signalé</option><option>Qualifié</option><option>En intervention</option><option>Résolu</option></select><input class="input" type="date" aria-label="Depuis" value="2026-09-01"></div>
    <div class="table-wrap"><table><thead><tr><th>Incident</th><th>Type</th><th>Infrastructure liée</th><th>Gravité</th><th>Statut</th><th>Équipe</th><th>Date</th><th></th></tr></thead><tbody id="incTable"></tbody></table></div>
    <div class="foot"><span class="muted">Affichage 1 à 7 sur 248</span><div class="pager"><a href="{{ route('admin.incidents.index') }}">‹</a><a class="on" href="{{ route('admin.incidents.index') }}">1</a><a href="{{ route('admin.incidents.index') }}">2</a><a href="{{ route('admin.incidents.index') }}">3</a><a href="{{ route('admin.incidents.index') }}">›</a></div></div>
  </div>
</section>
@endsection
