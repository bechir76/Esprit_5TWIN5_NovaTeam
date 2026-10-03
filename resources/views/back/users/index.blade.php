@extends('layouts.back.app')

@section('title', 'Utilisateurs')

@section('content')
<section data-page="users" class="stack">
  <x-page-header title="Utilisateurs">
    <a class="btn btn-primary" href="{{ route('admin.users.create') }}">+ Ajouter un utilisateur</a>
  </x-page-header>
  <div class="grid kpis">
    <x-kpi-card label="Total" value="4 218" />
    <x-kpi-card label="Gestionnaires" value="38" />
    <x-kpi-card label="Techniciens" value="64" />
    <x-kpi-card label="Suspendus" value="12" />
  </div>
  <div class="card">
    <div class="filters"><input class="input grow" placeholder="Nom ou e-mail…" aria-label="Rechercher"><select class="input" aria-label="Rôle"><option>Tous les rôles</option><option>Administrateur</option><option>Gestionnaire réseau</option><option>Technicien terrain</option><option>Citoyen</option></select><select class="input" aria-label="Statut"><option>Tous les statuts</option><option>Actif</option><option>Suspendu</option></select></div>
    <div class="table-wrap"><table><thead><tr><th>Utilisateur</th><th>Rôle</th><th>Organisation</th><th>Statut</th><th>Inscription</th><th></th></tr></thead><tbody id="userTable"></tbody></table></div>
    <div class="foot"><span class="muted">Affichage 1 à 7 sur 4 218</span><div class="pager"><a href="{{ route('admin.users.index') }}">‹</a><a class="on" href="{{ route('admin.users.index') }}">1</a><a href="{{ route('admin.users.index') }}">2</a><a href="{{ route('admin.users.index') }}">3</a><a href="{{ route('admin.users.index') }}">›</a></div></div>
  </div>
</section>
@endsection
