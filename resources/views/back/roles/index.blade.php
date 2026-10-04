@extends('layouts.back.app')

@section('title', 'Rôles et permissions')

@section('content')
<section data-page="roles" class="stack">
  <x-page-header title="Rôles et permissions" />
  <div class="grid" style="grid-template-columns:minmax(220px,280px) minmax(0,1fr)" id="rolesGrid">
    <div class="card card-b role-list stack" style="gap:2px">
      @foreach ($roles as ['role' => $role, 'count' => $count])
      <a href="{{ route('admin.users.index', ['role' => $role->value]) }}" @class(['on' => $role === \App\Enums\Role::Gestionnaire]) title="Voir les utilisateurs « {{ $role->label() }} »"><span><strong>{{ $role->label() }}</strong><br><span class="hint">{{ $role->description() }}</span></span><span class="mono muted">{{ number_format($count, 0, ',', ' ') }}</span></a>
      @endforeach
    </div>
    <div class="card">
      <div class="card-h"><h2>Permissions · Gestionnaire réseau</h2><x-status-badge value="Lecture seule" variant="b-mute" /></div>
      {{-- Cases générées par back.js (name="permissions[Module][]") ; matrice d'exemple non modifiable --}}
      <fieldset disabled style="border:0;margin:0;padding:0;min-width:0">
        <div class="table-wrap"><table class="matrix" style="min-width:560px"><thead><tr><th>Module</th><th>Voir</th><th>Créer</th><th>Modifier</th><th>Supprimer</th></tr></thead><tbody id="matrix"></tbody></table></div>
      </fieldset>
    </div>
  </div>
</section>
@endsection
