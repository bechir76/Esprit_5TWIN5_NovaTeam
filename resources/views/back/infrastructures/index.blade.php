@extends('layouts.back.app')

@section('title', 'Infrastructures')

@section('content')
<section data-page="infras" class="stack">
  <x-page-header title="Infrastructures">
    <div class="row"><button class="btn btn-ghost" type="button">Exporter CSV</button><a class="btn btn-primary" href="{{ route('admin.infrastructures.create') }}">+ Ajouter</a></div>
  </x-page-header>
  <div class="card">
    <div class="filters"><input class="input grow" placeholder="Nom, code ouvrage…" aria-label="Rechercher"><select class="input" aria-label="Type"><option>Tous les types</option><option>Réservoir</option><option>Conduite</option><option>Station de pompage</option><option>Station de traitement</option><option>Forage</option></select><select class="input" aria-label="Zone"><option>Toutes les zones</option><option>Ariana</option><option>Tunis</option><option>Ben Arous</option></select><select class="input" aria-label="État"><option>Tous les états</option><option>Bon</option><option>À surveiller</option><option>Critique</option></select></div>
    <div class="table-wrap"><table><thead><tr><th>Code</th><th>Nom</th><th>Type</th><th>Zone</th><th>Capacité</th><th>État</th><th>Dernière inspection</th><th></th></tr></thead><tbody id="infraTable"></tbody></table></div>
    <div class="foot"><span class="muted">Affichage 1 à 8 sur 512</span><div class="pager"><a href="{{ route('admin.infrastructures.index') }}">‹</a><a class="on" href="{{ route('admin.infrastructures.index') }}">1</a><a href="{{ route('admin.infrastructures.index') }}">2</a><a href="{{ route('admin.infrastructures.index') }}">3</a><a href="{{ route('admin.infrastructures.index') }}">…</a><a href="{{ route('admin.infrastructures.index') }}">64</a><a href="{{ route('admin.infrastructures.index') }}">›</a></div></div>
  </div>
</section>
@endsection
