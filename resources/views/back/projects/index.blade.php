@extends('layouts.back.app')

@section('title', 'Projets de rénovation')

@section('content')
<section data-page="projets" class="stack">
  <x-page-header title="Projets de rénovation">
    <a class="btn btn-primary" href="{{ route('admin.projects.create') }}">+ Nouveau projet</a>
  </x-page-header>
  <div class="card">
    <div class="filters"><input class="input grow" placeholder="Rechercher un projet…" aria-label="Rechercher"><select class="input" aria-label="Statut"><option>Tous les statuts</option><option>Brouillon</option><option>En collecte</option><option>Financé</option><option>En travaux</option><option>Terminé</option></select></div>
    <div class="table-wrap"><table><thead><tr><th>Projet</th><th>Infrastructure</th><th>Budget</th><th>Financement</th><th>Échéance</th><th>Statut</th><th></th></tr></thead><tbody id="projTable"></tbody></table></div>
  </div>
</section>
@endsection
