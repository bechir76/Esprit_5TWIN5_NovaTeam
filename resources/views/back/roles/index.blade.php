@extends('layouts.back.app')

@section('title', 'Rôles et permissions')

@section('content')
<section data-page="roles" class="stack">
  <x-page-header title="Rôles et permissions">
    <button class="btn btn-primary" type="button">+ Nouveau rôle</button>
  </x-page-header>
  <div class="grid" style="grid-template-columns:minmax(220px,280px) minmax(0,1fr)" id="rolesGrid">
    <div class="card card-b role-list stack" style="gap:2px">
      <a href="{{ route('admin.roles.index') }}"><span><strong>Administrateur</strong><br><span class="hint">Accès complet</span></span><span class="mono muted">4</span></a>
      <a href="{{ route('admin.roles.index') }}" class="on"><span><strong>Gestionnaire réseau</strong><br><span class="hint">Municipalités, régies</span></span><span class="mono muted">38</span></a>
      <a href="{{ route('admin.roles.index') }}"><span><strong>Technicien terrain</strong><br><span class="hint">Interventions</span></span><span class="mono muted">64</span></a>
      <a href="{{ route('admin.roles.index') }}"><span><strong>Citoyen</strong><br><span class="hint">Espace public</span></span><span class="mono muted">4 112</span></a>
    </div>
    <form class="card" method="POST" action="{{ route('admin.roles.update', 2) }}">
      @csrf
      @method('PUT')
      <div class="card-h"><h2>Permissions · Gestionnaire réseau</h2><button class="btn btn-primary btn-sm" type="submit">Enregistrer</button></div>
      {{-- Cases générées par back.js : name="permissions[Module][]" --}}
      <div class="table-wrap"><table class="matrix" style="min-width:560px"><thead><tr><th>Module</th><th>Voir</th><th>Créer</th><th>Modifier</th><th>Supprimer</th></tr></thead><tbody id="matrix"></tbody></table></div>
    </form>
  </div>
</section>
@endsection
